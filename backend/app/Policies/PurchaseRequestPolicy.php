<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function view(User $user, PurchaseRequest $pr): bool
    {
        return $this->sameCompany($user, $pr->company_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PurchaseRequest $pr): bool
    {
        return $pr->status === 'draft' && $this->sameCompany($user, $pr->company_id);
    }

    public function delete(User $user, PurchaseRequest $pr): bool
    {
        return $pr->status === 'draft' && $this->sameCompany($user, $pr->company_id);
    }

    public function approve(User $user, PurchaseRequest $pr): bool
    {
        return $this->sameCompany($user, $pr->company_id);
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
