<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function view(User $user, GoodsReceipt $grn): bool
    {
        return $this->sameCompany($user, $grn->purchaseOrder->company_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function complete(User $user, GoodsReceipt $grn): bool
    {
        return $grn->status === 'draft' && $this->sameCompany($user, $grn->purchaseOrder->company_id);
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
