<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\SalesQuotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Purchasing\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class SalesQuotationService
{
    public function __construct(
        protected NumberSeriesService $numberSeries
    ) {}

    public function create(array $data, array $lines, User $creator): SalesQuotation
    {
        return DB::transaction(function () use ($data, $lines, $creator) {
            $data['quotation_number'] = $this->numberSeries->next('QT');
            $data['created_by'] = $creator->id;
            $data['status'] = 'draft';

            // Calculate totals using bcmath
            $subtotal = '0.0000';
            $taxTotal = '0.0000';
            $discountTotal = '0.0000';
            $total = '0.0000';

            $lineRecords = [];
            foreach ($lines as $line) {
                $qty = (string) $line['quantity'];
                $price = (string) $line['unit_price'];
                $disc = (string) ($line['discount'] ?? '0.0000');
                $taxRate = (string) ($line['tax_rate'] ?? '0.0000');

                // subtotal = qty * price
                $lineSub = bcmul($qty, $price, 4);
                // line total before tax = subtotal - discount
                $lineBeforeTax = bcsub($lineSub, $disc, 4);
                // line tax = line total before tax * tax rate
                $lineTax = bcmul($lineBeforeTax, $taxRate, 4);
                // line total = line total before tax + line tax
                $lineTotal = bcadd($lineBeforeTax, $lineTax, 4);

                $subtotal = bcadd($subtotal, $lineSub, 4);
                $discountTotal = bcadd($discountTotal, $disc, 4);
                $taxTotal = bcadd($taxTotal, $lineTax, 4);
                $total = bcadd($total, $lineTotal, 4);

                $lineRecords[] = [
                    'product_id' => $line['product_id'],
                    'quantity' => $qty,
                    'unit_id' => $line['unit_id'],
                    'unit_price' => $price,
                    'discount' => $disc,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ];
            }

            $data['subtotal'] = $subtotal;
            $data['discount'] = $discountTotal;
            $data['tax'] = $taxTotal;
            $data['total'] = $total;

            $quotation = SalesQuotation::create($data);
            $quotation->lines()->createMany($lineRecords);

            return $quotation;
        });
    }

    public function transition(SalesQuotation $quotation, string $action, User $actor): SalesQuotation
    {
        return DB::transaction(function () use ($quotation, $action, $actor) {
            $oldStatus = $quotation->status;

            switch ($action) {
                case 'send':
                    if ($oldStatus !== 'draft') {
                        throw new InvalidStatusTransitionException('SalesQuotation', $oldStatus, 'sent');
                    }
                    $quotation->status = 'sent';
                    break;
                case 'accept':
                    if ($oldStatus !== 'sent') {
                        throw new InvalidStatusTransitionException('SalesQuotation', $oldStatus, 'accepted');
                    }
                    $quotation->status = 'accepted';
                    break;
                case 'reject':
                    if ($oldStatus !== 'sent') {
                        throw new InvalidStatusTransitionException('SalesQuotation', $oldStatus, 'rejected');
                    }
                    $quotation->status = 'rejected';
                    break;
                case 'expire':
                    if (!in_array($oldStatus, ['draft', 'sent'])) {
                        throw new InvalidStatusTransitionException('SalesQuotation', $oldStatus, 'expired');
                    }
                    $quotation->status = 'expired';
                    break;
                default:
                    throw new InvalidStatusTransitionException('SalesQuotation', $oldStatus, $action);
            }

            $quotation->save();
            return $quotation;
        });
    }

    public function convertToSalesOrder(SalesQuotation $quotation, ?string $expectedDeliveryDate, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($quotation, $expectedDeliveryDate, $actor) {
            // Lock quotation for update to prevent concurrent duplicate conversion
            $quotation = SalesQuotation::where('id', $quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status !== 'accepted') {
                throw new InvalidStatusTransitionException('SalesQuotation', $quotation->status, 'converted');
            }

            // Check if already converted (by looking up sales_orders where sales_quotation_id matches)
            $exists = SalesOrder::where('sales_quotation_id', $quotation->id)->exists();
            if ($exists) {
                throw new InvalidStatusTransitionException('SalesQuotation', 'accepted', 'already_converted');
            }

            // Create Sales Order
            $orderNumber = $this->numberSeries->next('SO');
            $order = SalesOrder::create([
                'order_number' => $orderNumber,
                'customer_id' => $quotation->customer_id,
                'company_id' => $quotation->company_id,
                'branch_id' => $quotation->branch_id,
                'sales_quotation_id' => $quotation->id,
                'created_by' => $actor->id,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $expectedDeliveryDate,
                'status' => 'draft',
                'notes' => "Converted from quotation {$quotation->quotation_number}.",
                'subtotal' => $quotation->subtotal,
                'discount' => $quotation->discount,
                'tax' => $quotation->tax,
                'total' => $quotation->total,
            ]);

            // Copy lines
            $orderLines = [];
            foreach ($quotation->lines as $line) {
                $orderLines[] = [
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'unit_price' => $line->unit_price,
                    'discount' => $line->discount,
                    'tax_rate' => $line->tax_rate,
                    'tax_amount' => $line->tax_amount,
                    'line_total' => $line->line_total,
                    'delivered_quantity' => '0.0000',
                    'invoiced_quantity' => '0.0000',
                ];
            }
            $order->lines()->createMany($orderLines);

            // Update quotation status to track it's been converted
            // (Accepted -> Converted? The prompt says "Draft -> Sent -> Accepted / Rejected / Expired. accepted quotation converts to sales order."
            // We can keep it "accepted" or update status to mark it, but let's keep status "accepted" as specified.)
            
            return $order;
        });
    }
}
