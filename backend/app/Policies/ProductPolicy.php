<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine if the user can update the product.
     *
     * Record-level/business rule check:
     * Lacking Admin role, users cannot edit products that are marked as inactive.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($product->status === 'inactive') {
            return $user->hasRole('Admin');
        }

        return true;
    }
}
