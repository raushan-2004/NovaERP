<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\DeliveryLine;
use App\Models\User;
use App\Services\Purchasing\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        protected NumberSeriesService $numberSeries
    ) {}

    public function create(SalesOrder $order, string $invoiceDate, string $dueDate, array $lines, User $creator): SalesInvoice
    {
        return DB::transaction(function () use ($order, $invoiceDate, $dueDate, $lines, $creator) {
            // Lock Sales Order
            $order = SalesOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (in_array($order->status, ['draft', 'submitted', 'cancelled'])) {
                throw new InvalidStatusTransitionException('SalesInvoice', $order->status, 'draft');
            }

            $invoiceNumber = $this->numberSeries->next('INV');
            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $order->customer_id,
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'sales_order_id' => $order->id,
                'created_by' => $creator->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => 'draft',
                'notes' => null,
                'subtotal' => '0.0000',
                'discount' => '0.0000',
                'tax' => '0.0000',
                'total' => '0.0000',
                'amount_paid' => '0.0000',
                'amount_due' => '0.0000',
            ]);

            $subtotal = '0.0000';
            $taxTotal = '0.0000';
            $discountTotal = '0.0000';
            $total = '0.0000';

            $invoiceLines = [];
            foreach ($lines as $line) {
                $soLineId = $line['sales_order_line_id'];
                $qtyToBill = (string) $line['quantity'];

                if (bccomp($qtyToBill, '0', 4) <= 0) {
                    throw new InvalidStatusTransitionException('SalesInvoiceLine', 'quantity', 'lte_zero');
                }

                $soLine = SalesOrderLine::where('id', $soLineId)
                    ->where('sales_order_id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Double invoicing check: remaining invoiceable qty = delivered_quantity - already_invoiced_quantity (from issued/paid invoices)
                // Wait! Let's calculate how much has been invoiced in all ISSUED / PARTIALLY PAID / PAID invoices.
                $alreadyInvoiced = SalesInvoiceLine::join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_lines.sales_invoice_id')
                    ->where('sales_invoice_lines.sales_order_line_id', $soLineId)
                    ->whereIn('sales_invoices.status', ['issued', 'partially_paid', 'paid'])
                    ->sum('sales_invoice_lines.quantity');

                $maxInvoiceable = bcsub((string) $soLine->delivered_quantity, (string) $alreadyInvoiced, 4);

                if (bccomp($qtyToBill, $maxInvoiceable, 4) > 0) {
                    throw new InvalidStatusTransitionException('SalesInvoiceLine', $maxInvoiceable, $qtyToBill);
                }

                $lineSub = bcmul($qtyToBill, (string) $soLine->unit_price, 4);
                // Pro-rate discount based on quantity? Or simple line discount?
                // For simplicity, let's allow explicit line discount amount, or calculate it based on PO line discount ratio.
                // Let's calculate: discount = (soLine.discount / soLine.quantity) * qtyToBill
                $disc = '0.0000';
                if (bccomp((string) $soLine->quantity, '0', 4) > 0) {
                    $discPerItem = bcdiv((string) $soLine->discount, (string) $soLine->quantity, 4);
                    $disc = bcmul($discPerItem, $qtyToBill, 4);
                }

                $lineBeforeTax = bcsub($lineSub, $disc, 4);
                $lineTax = bcmul($lineBeforeTax, (string) $soLine->tax_rate, 4);
                $lineTotal = bcadd($lineBeforeTax, $lineTax, 4);

                $subtotal = bcadd($subtotal, $lineSub, 4);
                $discountTotal = bcadd($discountTotal, $disc, 4);
                $taxTotal = bcadd($taxTotal, $lineTax, 4);
                $total = bcadd($total, $lineTotal, 4);

                $invoiceLines[] = [
                    'product_id' => $soLine->product_id,
                    'quantity' => $qtyToBill,
                    'unit_id' => $soLine->unit_id,
                    'unit_price' => $soLine->unit_price,
                    'discount' => $disc,
                    'tax_rate' => $soLine->tax_rate,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                    'sales_order_line_id' => $soLineId,
                    'delivery_line_id' => $line['delivery_line_id'] ?? null,
                ];
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount' => $discountTotal,
                'tax' => $taxTotal,
                'total' => $total,
                'amount_due' => $total,
            ]);

            $invoice->lines()->createMany($invoiceLines);

            return $invoice;
        });
    }

    public function transition(SalesInvoice $invoice, string $action, User $actor): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $action, $actor) {
            $invoice = SalesInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $invoice->status;

            if ($action === 'issue') {
                if ($oldStatus !== 'draft') {
                    throw new InvalidStatusTransitionException('SalesInvoice', $oldStatus, 'issued');
                }

                // Increase invoiced_quantity on SalesOrderLine records
                foreach ($invoice->lines as $line) {
                    if ($line->sales_order_line_id) {
                        $soLine = SalesOrderLine::where('id', $line->sales_order_line_id)->lockForUpdate()->firstOrFail();
                        
                        // We must double check the invariant: invoiced_quantity <= delivered_quantity
                        $newInvoiced = bcadd((string) $soLine->invoiced_quantity, (string) $line->quantity, 4);
                        if (bccomp($newInvoiced, (string) $soLine->delivered_quantity, 4) > 0) {
                            throw new InvalidStatusTransitionException('SalesInvoiceLine', 'invoiced', 'exceeds_delivered');
                        }

                        $soLine->invoiced_quantity = $newInvoiced;
                        $soLine->save();
                    }
                }

                $invoice->status = 'issued';
                $invoice->save();
            } else {
                throw new InvalidStatusTransitionException('SalesInvoice', $oldStatus, $action);
            }

            return $invoice;
        });
    }
}
