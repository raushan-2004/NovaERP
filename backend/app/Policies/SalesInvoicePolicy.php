<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;

class SalesInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_invoices.view');
    }

    public function view(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermission('sales_invoices.view') && $user->company_id === $invoice->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_invoices.create');
    }

    public function issue(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermission('sales_invoices.issue') && 
            $user->company_id === $invoice->company_id;
    }
}
