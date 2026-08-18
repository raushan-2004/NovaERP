<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Explicitly register policies
        Gate::policy(\App\Models\Product::class, \App\Policies\ProductPolicy::class);

        // 1. Super Admin permission-level bypass in Gate::before()
        Gate::before(function (User $user, string $ability, array $args) {
            // If checking a record-level policy (arguments exist), do not bypass.
            // Let the explicit Policy verify business ownership rules.
            if (! empty($args)) {
                return null;
            }

            // Super Admin bypasses general permission checks
            if ($user->hasRole('Super Admin')) {
                return true;
            }

            return null; // Fall through to defined gates/policies
        });

        // 2. Dynamically define gates for all system permissions
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'organization.view', 'organization.create', 'organization.update', 'organization.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
        ];

        foreach ($permissions as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->hasPermission($permission);
            });
        }
    }
}
