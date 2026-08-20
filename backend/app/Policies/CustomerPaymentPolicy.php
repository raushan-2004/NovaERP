<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customer_payments.view');
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $user->hasPermission('customer_payments.view') && $user->company_id === $payment->customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer_payments.create');
    }
}
