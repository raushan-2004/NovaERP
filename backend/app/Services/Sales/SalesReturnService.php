<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public function __construct(
        protected NumberSeriesService $numberSeries,
        protected InventoryService $inventoryService
    ) {}

    public function create(Delivery $delivery, int $warehouseId, string $returnedDate, string $reason, array $lines, User $creator): SalesReturn
    {
        return DB::transaction(function () use ($delivery, $warehouseId, $returnedDate, $reason, $lines, $creator) {
            // Lock delivery to make sure it's completed
            $delivery = Delivery::where('id', $delivery->id)->lockForUpdate()->firstOrFail();

            if ($delivery->status !== 'completed') {
                throw new InvalidStatusTransitionException('Delivery', $delivery->status, 'completed');
            }

            // Verify warehouse
            $warehouse = Warehouse::with('branch')->findOrFail($warehouseId);
            if ($warehouse->branch->company_id !== $delivery->customer->company_id) {
                throw new InvalidStatusTransitionException('SalesReturn', 'company', 'mismatch');
            }
            if ($warehouse->status !== 'active') {
                throw new InvalidStatusTransitionException('SalesReturn', 'warehouse', 'inactive');
            }

            $returnNumber = $this->numberSeries->next('SR');
            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'customer_id' => $delivery->customer_id,
                'sales_order_id' => $delivery->sales_order_id,
                'warehouse_id' => $warehouseId,
                'returned_by' => $creator->id,
                'returned_date' => $returnedDate,
                'reason' => $reason,
                'status' => 'draft',
            ]);

            $returnLines = [];
            foreach ($lines as $line) {
                $deliveryLineId = $line['delivery_line_id'];
                $qtyToReturn = (string) $line['quantity'];

                if (bccomp($qtyToReturn, '0', 4) <= 0) {
                    throw new InvalidStatusTransitionException('SalesReturnLine', 'quantity', 'lte_zero');
                }

                // Lock delivery line
                $deliveryLine = DeliveryLine::where('id', $deliveryLineId)
                    ->where('delivery_id', $delivery->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Calculate already returned quantity for this delivery line
                $alreadyReturned = SalesReturnLine::join('sales_returns', 'sales_returns.id', '=', 'sales_return_lines.sales_return_id')
                    ->where('sales_return_lines.delivery_line_id', $deliveryLineId)
                    ->whereIn('sales_returns.status', ['approved', 'completed']) // approved or completed returns count
                    ->sum('sales_return_lines.quantity');

                $eligible = bcsub((string) $deliveryLine->delivered_quantity, (string) $alreadyReturned, 4);

                if (bccomp($qtyToReturn, $eligible, 4) > 0) {
                    throw new InvalidStatusTransitionException('SalesReturnLine', $eligible, $qtyToReturn);
                }

                $returnLines[] = [
                    'product_id' => $deliveryLine->product_id,
                    'quantity' => $qtyToReturn,
                    'delivery_line_id' => $deliveryLineId,
                    'notes' => $line['notes'] ?? null,
                ];
            }

            $salesReturn->lines()->createMany($returnLines);

            return $salesReturn;
        });
    }

    public function transition(SalesReturn $return, string $action, User $actor): SalesReturn
    {
        return DB::transaction(function () use ($return, $action, $actor) {
            $return = SalesReturn::where('id', $return->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $return->status;

            switch ($action) {
                case 'approve':
                    if ($oldStatus !== 'draft') {
                        throw new InvalidStatusTransitionException('SalesReturn', $oldStatus, 'approved');
                    }
                    $return->status = 'approved';
                    break;

                case 'complete':
                    if ($oldStatus !== 'approved') {
                        throw new InvalidStatusTransitionException('SalesReturn', $oldStatus, 'completed');
                    }

                    // Enforce warehouse scope active check
                    $warehouse = Warehouse::where('id', $return->warehouse_id)->firstOrFail();
                    if ($warehouse->status !== 'active') {
                        throw new InvalidStatusTransitionException('SalesReturn', 'warehouse', 'inactive');
                    }

                    // Process inventory receive
                    foreach ($return->lines as $line) {
                        $product = Product::findOrFail($line->product_id);
                        $this->inventoryService->receive(
                            $product,
                            $warehouse,
                            (string) $line->quantity,
                            SalesReturn::class,
                            $return->id,
                            "Received from sales return {$return->return_number}",
                            $actor,
                            'sale_return' // Movement type sale_return
                        );
                    }

                    $return->status = 'completed';
                    break;

                case 'cancel':
                    if (!in_array($oldStatus, ['draft', 'approved'])) {
                        throw new InvalidStatusTransitionException('SalesReturn', $oldStatus, 'cancelled');
                    }
                    $return->status = 'cancelled';
                    break;

                default:
                    throw new InvalidStatusTransitionException("Invalid action: {$action}");
            }

            $return->save();
            return $return;
        });
    }
}
