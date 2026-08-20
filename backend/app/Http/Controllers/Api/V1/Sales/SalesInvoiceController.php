<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesInvoiceRequest;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Services\Sales\SalesInvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class SalesInvoiceController extends Controller implements HasMiddleware
{
    public function __construct(protected SalesInvoiceService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales_invoices.view',   only: ['index', 'show']),
            new Middleware('permission:sales_invoices.create', only: ['store']),
            new Middleware('permission:sales_invoices.issue',  only: ['issue']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SalesInvoice::with('customer', 'company', 'branch', 'creator', 'lines')
            ->where('company_id', $user->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Sales invoices retrieved successfully'
        );
    }

    public function store(SalesInvoiceRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $order = SalesOrder::findOrFail($data['sales_order_id']);
        Gate::authorize('view', $order);

        $invoice = $this->service->create(
            $order,
            $data['invoice_date'],
            $data['due_date'],
            $data['lines'],
            $user
        );

        return ApiResponse::created($invoice, 'Sales invoice created successfully');
    }

    public function show(SalesInvoice $salesInvoice): JsonResponse
    {
        Gate::authorize('view', $salesInvoice);
        $salesInvoice->load('customer', 'company', 'branch', 'creator', 'lines.product', 'lines.unit');
        return ApiResponse::success($salesInvoice, 'Sales invoice retrieved successfully');
    }

    public function issue(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        Gate::authorize('issue', $salesInvoice);
        $salesInvoice = $this->service->transition($salesInvoice, 'issue', $request->user());
        return ApiResponse::success($salesInvoice, 'Sales invoice issued successfully');
    }
}
