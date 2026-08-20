<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesReturn;
use App\Models\User;

class SalesReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_returns.view');
    }

    public function view(User $user, SalesReturn $return): bool
    {
        return $user->hasPermission('sales_returns.view') && $user->company_id === $return->customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_returns.create');
    }

    public function approve(User $user, SalesReturn $return): bool
    {
        return $user->hasPermission('sales_returns.approve') && $user->company_id === $return->customer->company_id;
    }
}
