<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function view(User $user, PurchaseOrder $po): bool
    {
        return $this->sameCompany($user, $po->company_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'draft' && $this->sameCompany($user, $po->company_id);
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'draft' && $this->sameCompany($user, $po->company_id);
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $this->sameCompany($user, $po->company_id);
    }

    public function send(User $user, PurchaseOrder $po): bool
    {
        return $this->sameCompany($user, $po->company_id);
    }

    private function sameCompany(User $user, int $companyId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        $employee = $user->employees()->first();
        return $employee && $employee->company_id === $companyId;
    }
}
