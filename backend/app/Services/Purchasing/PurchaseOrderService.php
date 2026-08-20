<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeries,
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Valid PO transitions:
     *   draft              → submitted | cancelled
     *   submitted          → approved  | cancelled
     *   approved           → sent      | cancelled
     *   sent               → partially_received | fully_received | cancelled
     *   partially_received → fully_received
     *   fully_received     → closed
     *   closed             → (terminal)
     *   cancelled          → (terminal)
     */
    private const ALLOWED_TRANSITIONS = [
        'draft'              => ['submitted', 'cancelled'],
        'submitted'          => ['approved', 'cancelled'],
        'approved'           => ['sent', 'cancelled'],
        'sent'               => ['partially_received', 'fully_received', 'cancelled'],
        'partially_received' => ['fully_received'],
        'fully_received'     => ['closed'],
        'closed'             => [],
        'cancelled'          => [],
    ];

    public function create(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor) {
            $po = PurchaseOrder::create([
                'po_number'               => $this->numberSeries->next('PO'),
                'company_id'              => $data['company_id'],
                'branch_id'               => $data['branch_id'],
                'supplier_id'             => $data['supplier_id'],
                'purchase_request_id'     => $data['purchase_request_id'] ?? null,
                'created_by'              => $actor->id,
                'order_date'              => $data['order_date'],
                'expected_delivery_date'  => $data['expected_delivery_date'] ?? null,
                'status'                  => 'draft',
                'notes'                   => $data['notes'] ?? null,
                'subtotal'                => '0.0000',
                'tax_amount'              => '0.0000',
                'total_amount'            => '0.0000',
            ]);

            $this->syncLines($po, $data['lines']);

            return $po->load('lines.product', 'lines.unit', 'supplier', 'company', 'branch', 'createdBy');
        });
    }

    public function createFromPr(PurchaseRequest $pr, array $poData, User $actor): PurchaseOrder
    {
        $pr->load('lines');

        $lines = $pr->lines->map(fn($l) => [
            'product_id' => $l->product_id,
            'unit_id'    => $l->unit_id,
            'quantity'   => (string) $l->quantity,
            'unit_price' => $poData['unit_price_map'][$l->product_id] ?? '0.0000',
            'tax_rate'   => $poData['tax_rate_map'][$l->product_id] ?? '0.0000',
        ])->toArray();

        return $this->create(array_merge($poData, [
            'purchase_request_id' => $pr->id,
            'lines'               => $lines,
        ]), $actor);
    }

    public function submit(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, 'submitted');
    }

    public function approve(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, 'approved');
    }

    public function send(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, 'sent');
    }

    public function close(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, 'closed');
    }

    public function cancel(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, 'cancelled');
    }

    /**
     * Create and complete a Goods Receipt, updating inventory and PO line quantities.
     */
    public function receiveGoods(PurchaseOrder $po, array $receiptData, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $receiptData, $actor) {
            $warehouse = Warehouse::findOrFail($receiptData['warehouse_id']);

            $grn = GoodsReceipt::create([
                'grn_number'        => $this->numberSeries->next('GRN'),
                'purchase_order_id' => $po->id,
                'warehouse_id'      => $warehouse->id,
                'received_by'       => $actor->id,
                'received_date'     => $receiptData['received_date'],
                'status'            => 'draft',
                'notes'             => $receiptData['notes'] ?? null,
            ]);

            foreach ($receiptData['lines'] as $lineData) {
                /** @var PurchaseOrderLine $poLine */
                $poLine  = PurchaseOrderLine::findOrFail($lineData['purchase_order_line_id']);
                $product = Product::findOrFail($poLine->product_id);

                $grnLine = GoodsReceiptLine::create([
                    'goods_receipt_id'       => $grn->id,
                    'purchase_order_line_id' => $poLine->id,
                    'product_id'             => $product->id,
                    'quantity_received'      => $lineData['quantity_received'],
                    'notes'                  => $lineData['notes'] ?? null,
                ]);

                // Update inventory
                if ($product->track_inventory) {
                    $this->inventoryService->receive(
                        $product,
                        $warehouse,
                        (string) $lineData['quantity_received'],
                        GoodsReceipt::class,
                        $grn->id,
                        null,
                        $actor
                    );
                }

                // Update received_quantity on PO line
                $poLine->received_quantity = bcadd(
                    (string) $poLine->received_quantity,
                    (string) $lineData['quantity_received'],
                    4
                );
                $poLine->save();
            }

            // Mark GRN as completed
            $grn->status = 'completed';
            $grn->save();

            // Recalculate PO status
            $this->recalculatePoStatus($po);

            return $grn->load('lines.product', 'lines.purchaseOrderLine', 'warehouse', 'receivedBy');
        });
    }

    /**
     * Process a purchase return: issues stock back out using purchase_return movement type.
     */
    public function processReturn(GoodsReceipt $grn, array $returnData, User $actor): PurchaseReturn
    {
        return DB::transaction(function () use ($grn, $returnData, $actor) {
            $grn->load('warehouse', 'purchaseOrder.supplier');

            $return = PurchaseReturn::create([
                'return_number'    => $this->numberSeries->next('RTN'),
                'goods_receipt_id' => $grn->id,
                'supplier_id'      => $grn->purchaseOrder->supplier_id,
                'returned_by'      => $actor->id,
                'return_date'      => $returnData['return_date'],
                'reason'           => $returnData['reason'],
                'status'           => 'draft',
            ]);

            foreach ($returnData['lines'] as $lineData) {
                $grnLine = GoodsReceiptLine::findOrFail($lineData['goods_receipt_line_id']);
                $product = Product::findOrFail($grnLine->product_id);

                $return->lines()->create([
                    'goods_receipt_line_id' => $grnLine->id,
                    'product_id'            => $product->id,
                    'quantity_returned'     => $lineData['quantity_returned'],
                    'notes'                 => $lineData['notes'] ?? null,
                ]);

                if ($product->track_inventory) {
                    $this->inventoryService->issue(
                        $product,
                        $grn->warehouse,
                        (string) $lineData['quantity_returned'],
                        'purchase_return',
                        PurchaseReturn::class,
                        $return->id,
                        $returnData['reason'],
                        $actor
                    );
                }
            }

            $return->status = 'completed';
            $return->save();

            return $return->load('lines.product', 'goodsReceipt', 'supplier', 'returnedBy');
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function transition(PurchaseOrder $po, string $to): PurchaseOrder
    {
        $from    = $po->status;
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidStatusTransitionException('Purchase Order', $from, $to);
        }

        $po->status = $to;
        $po->save();

        return $po->fresh();
    }

    private function syncLines(PurchaseOrder $po, array $lines): void
    {
        $subtotal  = '0.0000';
        $taxAmount = '0.0000';

        foreach ($lines as $line) {
            $qty        = (string) $line['quantity'];
            $unitPrice  = (string) $line['unit_price'];
            $taxRate    = (string) ($line['tax_rate'] ?? '0.0000');
            $lineNet    = bcmul($qty, $unitPrice, 4);
            $lineTax    = bcmul($lineNet, $taxRate, 4);
            $lineTotal  = bcadd($lineNet, $lineTax, 4);

            $po->lines()->create([
                'product_id'        => $line['product_id'],
                'unit_id'           => $line['unit_id'],
                'quantity'          => $qty,
                'unit_price'        => $unitPrice,
                'tax_rate'          => $taxRate,
                'tax_amount'        => $lineTax,
                'line_total'        => $lineTotal,
                'received_quantity' => '0.0000',
            ]);

            $subtotal  = bcadd($subtotal, $lineNet, 4);
            $taxAmount = bcadd($taxAmount, $lineTax, 4);
        }

        $po->subtotal    = $subtotal;
        $po->tax_amount  = $taxAmount;
        $po->total_amount = bcadd($subtotal, $taxAmount, 4);
        $po->save();
    }

    private function recalculatePoStatus(PurchaseOrder $po): void
    {
        $po->load('lines');

        $allFullyReceived   = true;
        $anyPartialReceived = false;

        foreach ($po->lines as $line) {
            $remaining = bcsub((string) $line->quantity, (string) $line->received_quantity, 4);
            if (bccomp($remaining, '0', 4) > 0) {
                $allFullyReceived = false;
            }
            if (bccomp((string) $line->received_quantity, '0', 4) > 0) {
                $anyPartialReceived = true;
            }
        }

        if ($allFullyReceived && in_array($po->status, ['sent', 'partially_received'], true)) {
            $po->status = 'fully_received';
            $po->save();
        } elseif ($anyPartialReceived && $po->status === 'sent') {
            $po->status = 'partially_received';
            $po->save();
        }
    }
}
