<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\SalesInvoice;
use App\Models\CustomerPayment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Approved Sales Orders Total Amount
        $salesOrderQuery = SalesOrder::where('company_id', $companyId);
        $approvedOrdersTotal = (string) $salesOrderQuery->clone()->where('status', 'approved')->sum('total');
        $ordersCount = $salesOrderQuery->clone()->count();

        // Total Outstanding Invoices Amount (issued or partially_paid status)
        $outstandingInvoicesTotal = (string) SalesInvoice::where('company_id', $companyId)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->sum('amount_due');

        // Total Invoiced Amount
        $totalInvoiced = (string) SalesInvoice::where('company_id', $companyId)
            ->whereIn('status', ['issued', 'partially_paid', 'paid'])
            ->sum('total');

        // Payments Received Amount (total customer payments)
        $paymentsReceivedTotal = (string) CustomerPayment::whereHas('customer', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->sum('amount');

        return ApiResponse::success([
            'approved_orders_total' => $approvedOrdersTotal,
            'orders_count' => $ordersCount,
            'outstanding_invoices_total' => $outstandingInvoicesTotal,
            'total_invoiced' => $totalInvoiced,
            'payments_received_total' => $paymentsReceivedTotal,
        ], 'Sales dashboard data retrieved successfully');
    }
}
