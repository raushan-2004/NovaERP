<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Purchasing\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    public function __construct(
        protected NumberSeriesService $numberSeries
    ) {}

    public function record(int $invoiceId, string $amount, string $paymentMethod, ?string $reference, ?string $notes, User $recorder): CustomerPayment
    {
        return DB::transaction(function () use ($invoiceId, $amount, $paymentMethod, $reference, $notes, $recorder) {
            if (bccomp($amount, '0', 4) <= 0) {
                throw new InvalidStatusTransitionException('CustomerPayment', 'amount', 'lte_zero');
            }

            // Lock the invoice
            $invoice = SalesInvoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail();

            if (!in_array($invoice->status, ['issued', 'partially_paid'])) {
                throw new InvalidStatusTransitionException('SalesInvoice', $invoice->status, 'paid');
            }

            $due = (string) $invoice->amount_due;
            if (bccomp($amount, $due, 4) > 0) {
                throw new InvalidStatusTransitionException('CustomerPayment', $due, $amount);
            }

            // Create Payment record
            $paymentNumber = $this->numberSeries->next('PAY');
            $payment = CustomerPayment::create([
                'payment_number' => $paymentNumber,
                'customer_id' => $invoice->customer_id,
                'sales_invoice_id' => $invoice->id,
                'recorded_by' => $recorder->id,
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            // Update Invoice totals
            $newPaid = bcadd((string) $invoice->amount_paid, $amount, 4);
            $newDue = bcsub((string) $invoice->total, $newPaid, 4);

            $invoice->amount_paid = $newPaid;
            $invoice->amount_due = $newDue;

            if (bccomp($newDue, '0', 4) === 0) {
                $invoice->status = 'paid';
            } else {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();

            return $payment;
        });
    }
}
