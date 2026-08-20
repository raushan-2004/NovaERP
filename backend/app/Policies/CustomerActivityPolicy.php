<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerActivity;
use App\Models\User;

class CustomerActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, CustomerActivity $activity): bool
    {
        return $user->hasPermission('crm.view') && $user->company_id === $activity->customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.create');
    }

    public function update(User $user, CustomerActivity $activity): bool
    {
        return $user->hasPermission('crm.update') && $user->company_id === $activity->customer->company_id;
    }
}
