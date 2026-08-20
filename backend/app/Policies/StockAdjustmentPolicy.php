<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $this->hasWarehouseAccess($user, $adjustment->warehouse_id);
    }

    public function create(User $user): bool
    {
        return true; // permission-level check handles this
    }

    private function hasWarehouseAccess(User $user, int $warehouseId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        $employee = $user->employees()->first();
        if (! $employee) {
            return false;
        }
        return \App\Models\Warehouse::where('id', $warehouseId)
            ->whereHas('branch', fn($q) => $q->where('company_id', $employee->company_id))
            ->exists();
    }
}
