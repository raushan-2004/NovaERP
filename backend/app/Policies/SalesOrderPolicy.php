<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_orders.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales_orders.view') && $user->company_id === $order->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_orders.create');
    }

    public function update(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales_orders.update') && 
            $user->company_id === $order->company_id;
    }

    public function delete(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales_orders.delete') && 
            $user->company_id === $order->company_id;
    }

    public function approve(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales_orders.approve') && $user->company_id === $order->company_id;
    }
}
