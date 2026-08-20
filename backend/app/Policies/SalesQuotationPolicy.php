<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesQuotation;
use App\Models\User;

class SalesQuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('quotations.view');
    }

    public function view(User $user, SalesQuotation $quotation): bool
    {
        return $user->hasPermission('quotations.view') && $user->company_id === $quotation->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('quotations.create');
    }

    public function update(User $user, SalesQuotation $quotation): bool
    {
        return $user->hasPermission('quotations.update') && 
            $user->company_id === $quotation->company_id;
    }

    public function delete(User $user, SalesQuotation $quotation): bool
    {
        return $user->hasPermission('quotations.delete') && 
            $user->company_id === $quotation->company_id;
    }

    public function approve(User $user, SalesQuotation $quotation): bool
    {
        return $user->hasPermission('quotations.approve') && $user->company_id === $quotation->company_id;
    }
}
