<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CustomerPaymentRequest;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Services\Sales\CustomerPaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class CustomerPaymentController extends Controller implements HasMiddleware
{
    public function __construct(protected CustomerPaymentService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:customer_payments.view',   only: ['index', 'show']),
            new Middleware('permission:customer_payments.create', only: ['store']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CustomerPayment::with('customer', 'salesInvoice', 'recorder')
            ->whereHas('customer', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Customer payments retrieved successfully'
        );
    }

    public function store(CustomerPaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $invoice = SalesInvoice::findOrFail($data['sales_invoice_id']);
        Gate::authorize('view', $invoice);

        $payment = $this->service->record(
            (int) $data['sales_invoice_id'],
            (string) $data['amount'],
            $data['payment_method'],
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $user
        );

        return ApiResponse::created($payment, 'Customer payment recorded successfully');
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        Gate::authorize('view', $customerPayment);
        $customerPayment->load('customer', 'salesInvoice', 'recorder');
        return ApiResponse::success($customerPayment, 'Customer payment retrieved successfully');
    }
}
