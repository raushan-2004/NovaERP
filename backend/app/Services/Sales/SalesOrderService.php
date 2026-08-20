<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Purchasing\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        protected NumberSeriesService $numberSeries
    ) {}

    public function create(array $data, array $lines, User $creator): SalesOrder
    {
        return DB::transaction(function () use ($data, $lines, $creator) {
            $data['order_number'] = $this->numberSeries->next('SO');
            $data['created_by'] = $creator->id;
            $data['status'] = 'draft';

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

                $lineSub = bcmul($qty, $price, 4);
                $lineBeforeTax = bcsub($lineSub, $disc, 4);
                $lineTax = bcmul($lineBeforeTax, $taxRate, 4);
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
                    'delivered_quantity' => '0.0000',
                    'invoiced_quantity' => '0.0000',
                ];
            }

            $data['subtotal'] = $subtotal;
            $data['discount'] = $discountTotal;
            $data['tax'] = $taxTotal;
            $data['total'] = $total;

            $order = SalesOrder::create($data);
            $order->lines()->createMany($lineRecords);

            return $order;
        });
    }

    public function transition(SalesOrder $order, string $action, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $action, $actor) {
            $oldStatus = $order->status;

            switch ($action) {
                case 'submit':
                    if ($oldStatus !== 'draft') {
                        throw new InvalidStatusTransitionException('SalesOrder', $oldStatus, 'submitted');
                    }
                    $order->status = 'submitted';
                    break;
                case 'approve':
                    if ($oldStatus !== 'submitted') {
                        throw new InvalidStatusTransitionException('SalesOrder', $oldStatus, 'approved');
                    }
                    $order->status = 'approved';
                    break;
                case 'cancel':
                    if (!in_array($oldStatus, ['draft', 'submitted', 'approved'])) {
                        throw new InvalidStatusTransitionException('SalesOrder', $oldStatus, 'cancelled');
                    }
                    $order->status = 'cancelled';
                    break;
                default:
                    throw new InvalidStatusTransitionException('SalesOrder', $oldStatus, $action);
            }

            $order->save();
            return $order;
        });
    }
}
