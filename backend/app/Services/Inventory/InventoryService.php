<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Receive stock into a warehouse (e.g. from a Goods Receipt).
     * Creates a stock_ledger_entry and updates stock_balances.
     */
    public function receive(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        User $actor,
        string $movementType = 'purchase_receipt'
    ): StockLedgerEntry {
        return DB::transaction(function () use ($product, $warehouse, $quantity, $referenceType, $referenceId, $notes, $actor, $movementType) {
            $balance = $this->lockBalance($warehouse, $product);

            $balanceBefore = $balance->quantity;
            $balanceAfter  = bcadd($balanceBefore, $quantity, 4);

            $balance->quantity = $balanceAfter;
            $balance->save();

            return $this->writeEntry(
                $product, $warehouse,
                $movementType, $quantity,
                $balanceBefore, $balanceAfter,
                $referenceType, $referenceId,
                $notes, $actor
            );
        });
    }

    /**
     * Issue stock out of a warehouse (e.g. for a Purchase Return).
     * Enforces negative-stock policy.
     */
    public function issue(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        string $movementType,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        User $actor
    ): StockLedgerEntry {
        return DB::transaction(function () use ($product, $warehouse, $quantity, $movementType, $referenceType, $referenceId, $notes, $actor) {
            $balance = $this->lockBalance($warehouse, $product);

            $balanceBefore = $balance->quantity;
            $balanceAfter  = bcsub($balanceBefore, $quantity, 4);

            $this->guardNegativeStock($product, $warehouse, $balanceBefore, $quantity);

            $balance->quantity = $balanceAfter;
            $balance->save();

            return $this->writeEntry(
                $product, $warehouse,
                $movementType, $quantity,
                $balanceBefore, $balanceAfter,
                $referenceType, $referenceId,
                $notes, $actor
            );
        });
    }

    /**
     * Transfer stock between two warehouses atomically.
     *
     * Deadlock prevention: locks are acquired in deterministic order
     * (warehouse_id ASC, then product_id ASC) regardless of direction.
     */
    public function transfer(
        Warehouse $from,
        Warehouse $to,
        Product $product,
        string $quantity,
        StockTransfer $transfer,
        User $actor
    ): void {
        DB::transaction(function () use ($from, $to, $product, $quantity, $transfer, $actor) {
            // Build lock targets sorted deterministically to prevent deadlocks
            $targets = collect([
                ['warehouse' => $from, 'role' => 'from'],
                ['warehouse' => $to,   'role' => 'to'],
            ])->sortBy(fn($t) => $t['warehouse']->id);

            // Acquire locks in sorted order, ensuring both rows exist first
            $locked = [];
            foreach ($targets as $target) {
                $locked[$target['role']] = $this->lockBalance($target['warehouse'], $product);
            }

            /** @var StockBalance $fromBalance */
            $fromBalance = $locked['from'];
            /** @var StockBalance $toBalance */
            $toBalance   = $locked['to'];

            $fromBefore = $fromBalance->quantity;
            $toBefore   = $toBalance->quantity;

            $this->guardNegativeStock($product, $from, $fromBefore, $quantity);

            $fromAfter = bcsub($fromBefore, $quantity, 4);
            $toAfter   = bcadd($toBefore, $quantity, 4);

            $fromBalance->quantity = $fromAfter;
            $fromBalance->save();

            $toBalance->quantity = $toAfter;
            $toBalance->save();

            // Write both ledger entries in same transaction
            $this->writeEntry($product, $from, 'transfer_out', $quantity, $fromBefore, $fromAfter, StockTransfer::class, $transfer->id, null, $actor);
            $this->writeEntry($product, $to,   'transfer_in',  $quantity, $toBefore,   $toAfter,   StockTransfer::class, $transfer->id, null, $actor);
        });
    }

    /**
     * Apply a stock adjustment (positive = in, negative = out).
     * Creates a StockAdjustment record and writes the ledger entry.
     */
    public function adjust(
        Product $product,
        Warehouse $warehouse,
        string $adjustedQty,
        string $reason,
        User $actor
    ): StockAdjustment {
        return DB::transaction(function () use ($product, $warehouse, $adjustedQty, $reason, $actor) {
            $balance = $this->lockBalance($warehouse, $product);

            $balanceBefore = $balance->quantity;
            $balanceAfter  = bcadd($balanceBefore, $adjustedQty, 4);

            // For out-adjustments (negative), enforce negative-stock guard
            if (bccomp($adjustedQty, '0', 4) < 0) {
                $absQty = ltrim($adjustedQty, '-');
                $this->guardNegativeStock($product, $warehouse, $balanceBefore, $absQty);
            }

            $balance->quantity = $balanceAfter;
            $balance->save();

            $movementType = bccomp($adjustedQty, '0', 4) >= 0 ? 'adjustment_in' : 'adjustment_out';
            $absQty       = ltrim($adjustedQty, '-');

            $adjustment = StockAdjustment::create([
                'product_id'        => $product->id,
                'warehouse_id'      => $warehouse->id,
                'adjusted_quantity' => $adjustedQty,
                'reason'            => $reason,
                'adjusted_by'       => $actor->id,
                'adjusted_at'       => now(),
            ]);

            $this->writeEntry(
                $product, $warehouse,
                $movementType, $absQty,
                $balanceBefore, $balanceAfter,
                StockAdjustment::class, $adjustment->id,
                $reason, $actor
            );

            return $adjustment;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Ensure the stock balance row exists (atomic via insertOrIgnore),
     * then lock the row for update within the current transaction.
     */
    private function lockBalance(Warehouse $warehouse, Product $product): StockBalance
    {
        // insertOrIgnore is atomic — if the row already exists the insert is silently discarded
        DB::table('stock_balances')->insertOrIgnore([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity'     => '0.0000',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return StockBalance::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function guardNegativeStock(Product $product, Warehouse $warehouse, string $current, string $requested): void
    {
        if (bccomp($current, $requested, 4) < 0 && ! config('nova.allow_negative_stock')) {
            throw new InsufficientStockException(
                $product->sku,
                $warehouse->name,
                $current,
                $requested
            );
        }
    }

    private function writeEntry(
        Product $product,
        Warehouse $warehouse,
        string $movementType,
        string $quantity,
        string $balanceBefore,
        string $balanceAfter,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        User $actor
    ): StockLedgerEntry {
        return StockLedgerEntry::create([
            'product_id'     => $product->id,
            'warehouse_id'   => $warehouse->id,
            'movement_type'  => $movementType,
            'quantity'       => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'created_by'     => $actor->id,
            'occurred_at'    => now(),
        ]);
    }
}
