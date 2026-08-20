<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    public function view(User $user, PurchaseReturn $return): bool
    {
        return $this->sameCompany($user, $return->goodsReceipt->purchaseOrder->company_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function complete(User $user, PurchaseReturn $return): bool
    {
        return $return->status === 'draft'
            && $this->sameCompany($user, $return->goodsReceipt->purchaseOrder->company_id);
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
