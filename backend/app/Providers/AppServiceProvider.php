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
        Gate::policy(\App\Models\Product::class,         \App\Policies\ProductPolicy::class);
        Gate::policy(\App\Models\StockTransfer::class,   \App\Policies\StockTransferPolicy::class);
        Gate::policy(\App\Models\StockAdjustment::class, \App\Policies\StockAdjustmentPolicy::class);
        Gate::policy(\App\Models\PurchaseRequest::class, \App\Policies\PurchaseRequestPolicy::class);
        Gate::policy(\App\Models\PurchaseOrder::class,   \App\Policies\PurchaseOrderPolicy::class);
        Gate::policy(\App\Models\GoodsReceipt::class,    \App\Policies\GoodsReceiptPolicy::class);
        Gate::policy(\App\Models\PurchaseReturn::class,  \App\Policies\PurchaseReturnPolicy::class);
        Gate::policy(\App\Models\SalesQuotation::class,  \App\Policies\SalesQuotationPolicy::class);
        Gate::policy(\App\Models\SalesOrder::class,      \App\Policies\SalesOrderPolicy::class);
        Gate::policy(\App\Models\Delivery::class,        \App\Policies\DeliveryPolicy::class);
        Gate::policy(\App\Models\SalesReturn::class,     \App\Policies\SalesReturnPolicy::class);
        Gate::policy(\App\Models\SalesInvoice::class,    \App\Policies\SalesInvoicePolicy::class);
        Gate::policy(\App\Models\CustomerPayment::class, \App\Policies\CustomerPaymentPolicy::class);
        Gate::policy(\App\Models\CustomerActivity::class,\App\Policies\CustomerActivityPolicy::class);

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
            // RBAC
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            // Organization
            'organization.view', 'organization.create', 'organization.update', 'organization.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            // Master Data
            'products.view', 'products.create', 'products.update', 'products.delete',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            // Inventory
            'inventory.view', 'inventory.adjust',
            // Purchasing
            'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.delete',
            'purchase_requests.approve',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
            'purchase_orders.approve',
            'goods_receipts.view', 'goods_receipts.create',
            'purchase_returns.view', 'purchase_returns.create',
            // Stage 3 — Sales
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve', 'quotations.delete',
            'sales_orders.view', 'sales_orders.create', 'sales_orders.update', 'sales_orders.approve', 'sales_orders.delete',
            'deliveries.view', 'deliveries.create', 'deliveries.complete',
            'sales_returns.view', 'sales_returns.create', 'sales_returns.approve',
            'sales_invoices.view', 'sales_invoices.create', 'sales_invoices.issue',
            'customer_payments.view', 'customer_payments.create',
            // Stage 3 — CRM
            'crm.view', 'crm.create', 'crm.update',
        ];

        foreach ($permissions as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->hasPermission($permission);
            });
        }
    }
}

