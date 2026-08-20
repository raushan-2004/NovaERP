<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('deliveries.view');
    }

    public function view(User $user, Delivery $delivery): bool
    {
        return $user->hasPermission('deliveries.view') && $user->company_id === $delivery->customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('deliveries.create');
    }

    public function complete(User $user, Delivery $delivery): bool
    {
        return $user->hasPermission('deliveries.complete') && 
            $user->company_id === $delivery->customer->company_id && 
            $delivery->status === 'draft';
    }
}
