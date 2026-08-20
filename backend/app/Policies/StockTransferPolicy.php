<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->hasWarehouseAccess($user, $transfer->from_warehouse_id)
            || $this->hasWarehouseAccess($user, $transfer->to_warehouse_id);
    }

    public function create(User $user): bool
    {
        return true; // permission-level check (inventory.adjust) handles this
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $transfer->status === 'draft'
            && $this->hasWarehouseAccess($user, $transfer->from_warehouse_id);
    }

    public function complete(User $user, StockTransfer $transfer): bool
    {
        return $transfer->status === 'draft'
            && $this->hasWarehouseAccess($user, $transfer->from_warehouse_id);
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $transfer->status === 'draft'
            && $this->hasWarehouseAccess($user, $transfer->from_warehouse_id);
    }

    private function hasWarehouseAccess(User $user, int $warehouseId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        // For scoped users, verify the warehouse belongs to their company via branch
        $employee = $user->employees()->first();
        if (! $employee) {
            return false;
        }
        return \App\Models\Warehouse::where('id', $warehouseId)
            ->whereHas('branch', fn($q) => $q->where('company_id', $employee->company_id))
            ->exists();
    }
}
