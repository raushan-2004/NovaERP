<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\InsufficientStockException;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function __construct(
        protected NumberSeriesService $numberSeries,
        protected InventoryService $inventoryService
    ) {}

    public function create(SalesOrder $order, int $warehouseId, string $deliveryDate, array $lines, User $creator): Delivery
    {
        return DB::transaction(function () use ($order, $warehouseId, $deliveryDate, $lines, $creator) {
            // Lock order for update to ensure consistent status check and delivered quantities
            $order = SalesOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (!in_array($order->status, ['approved', 'partially_delivered'])) {
                throw new InvalidStatusTransitionException('SalesOrder', $order->status, 'approved');
            }

            // Verify warehouse exists and matches correct scopes
            $warehouse = Warehouse::with('branch')->findOrFail($warehouseId);
            if ($warehouse->branch->company_id !== $order->company_id || $warehouse->branch_id !== $order->branch_id) {
                throw new InvalidStatusTransitionException('Delivery', 'scope', 'mismatch');
            }

            if ($warehouse->status !== 'active') {
                throw new InvalidStatusTransitionException('Delivery', 'warehouse', 'inactive');
            }

            $deliveryNumber = $this->numberSeries->next('DEL');
            $delivery = Delivery::create([
                'delivery_number' => $deliveryNumber,
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'warehouse_id' => $warehouseId,
                'delivered_by' => $creator->id,
                'delivery_date' => $deliveryDate,
                'status' => 'draft',
                'notes' => null,
            ]);

            $deliveryLines = [];
            foreach ($lines as $line) {
                $soLineId = $line['sales_order_line_id'];
                $qtyDelivered = (string) $line['quantity'];

                if (bccomp($qtyDelivered, '0', 4) <= 0) {
                    throw new InvalidStatusTransitionException('DeliveryLine', 'quantity', 'lte_zero');
                }

                // Retrieve and lock SO line
                $soLine = SalesOrderLine::where('id', $soLineId)
                    ->where('sales_order_id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Calculate remaining order quantity
                $remaining = bcsub((string) $soLine->quantity, (string) $soLine->delivered_quantity, 4);

                if (bccomp($qtyDelivered, $remaining, 4) > 0) {
                    throw new InvalidStatusTransitionException('DeliveryLine', $remaining, $qtyDelivered);
                }

                $deliveryLines[] = [
                    'sales_order_line_id' => $soLineId,
                    'product_id' => $soLine->product_id,
                    'ordered_quantity' => $soLine->quantity,
                    'delivered_quantity' => $qtyDelivered,
                ];
            }

            $delivery->lines()->createMany($deliveryLines);

            return $delivery;
        });
    }

    public function complete(Delivery $delivery, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor) {
            // Lock delivery and associated order
            $delivery = Delivery::where('id', $delivery->id)->lockForUpdate()->firstOrFail();

            if ($delivery->status !== 'draft') {
                throw new InvalidStatusTransitionException('Delivery', $delivery->status, 'completed');
            }

            $order = SalesOrder::where('id', $delivery->sales_order_id)->lockForUpdate()->firstOrFail();
            $warehouse = Warehouse::where('id', $delivery->warehouse_id)->firstOrFail();

            // Enforce warehouse active status check
            if ($warehouse->status !== 'active') {
                throw new InvalidStatusTransitionException('Delivery', 'warehouse', 'inactive');
            }

            // Issue stock and update sales order lines
            foreach ($delivery->lines as $line) {
                $product = Product::findOrFail($line->product_id);
                
                // Issue inventory through InventoryService
                $this->inventoryService->issue(
                    $product,
                    $warehouse,
                    (string) $line->delivered_quantity,
                    'sale', // Movement type is sale
                    Delivery::class,
                    $delivery->id,
                    "Issued for delivery {$delivery->delivery_number}",
                    $actor
                );

                // Update SO Line delivered quantity
                $soLine = SalesOrderLine::where('id', $line->sales_order_line_id)->lockForUpdate()->firstOrFail();
                $newDelivered = bcadd((string) $soLine->delivered_quantity, (string) $line->delivered_quantity, 4);
                
                // Double check final invariant check
                if (bccomp($newDelivered, (string) $soLine->quantity, 4) > 0) {
                    throw new InvalidStatusTransitionException('DeliveryLine', 'quantity', 'exceeds_ordered');
                }

                $soLine->delivered_quantity = $newDelivered;
                $soLine->save();
            }

            // Recalculate Sales Order Status
            $allFullyDelivered = true;
            foreach ($order->lines as $l) {
                if (bccomp((string) $l->delivered_quantity, (string) $l->quantity, 4) < 0) {
                    $allFullyDelivered = false;
                    break;
                }
            }

            $order->status = $allFullyDelivered ? 'fully_delivered' : 'partially_delivered';
            $order->save();

            $delivery->status = 'completed';
            $delivery->save();

            return $delivery;
        });
    }
}
