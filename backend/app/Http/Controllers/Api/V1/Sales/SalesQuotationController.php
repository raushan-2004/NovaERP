<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesQuotationRequest;
use App\Models\SalesQuotation;
use App\Services\Sales\SalesQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class SalesQuotationController extends Controller implements HasMiddleware
{
    public function __construct(protected SalesQuotationService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:quotations.view',    only: ['index', 'show']),
            new Middleware('permission:quotations.create',  only: ['store', 'convertToSalesOrder']),
            new Middleware('permission:quotations.update',  only: ['update', 'send', 'accept', 'reject', 'expire']),
            new Middleware('permission:quotations.delete',  only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SalesQuotation::with('customer', 'company', 'branch', 'creator', 'lines')
            ->where('company_id', $user->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Sales quotations retrieved successfully'
        );
    }

    public function store(SalesQuotationRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $customer = \App\Models\Customer::findOrFail($data['customer_id']);
        if ($customer->company_id !== $user->company_id) {
            return response()->json(['message' => 'Forbidden customer company scope mismatch'], 403);
        }

        // Enforce user's company
        $data['company_id'] = $user->company_id;

        $quotation = $this->service->create($data, $data['lines'], $user);
        return ApiResponse::created($quotation, 'Sales quotation created successfully');
    }

    public function show(SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('view', $quotation);
        $quotation->load('customer', 'company', 'branch', 'creator', 'lines.product', 'lines.unit');
        return ApiResponse::success($quotation, 'Sales quotation retrieved successfully');
    }

    public function update(SalesQuotationRequest $request, SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('update', $quotation);
        
        $data = $request->validated();
        $quotation->update([
            'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
            'notes' => $data['notes'] ?? $quotation->notes,
        ]);

        return ApiResponse::success($quotation->fresh(), 'Sales quotation updated successfully');
    }

    public function destroy(SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('delete', $quotation);
        $quotation->delete();
        return ApiResponse::success(null, 'Sales quotation deleted successfully');
    }

    public function send(Request $request, SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('update', $quotation);
        $quotation = $this->service->transition($quotation, 'send', $request->user());
        return ApiResponse::success($quotation, 'Sales quotation sent successfully');
    }

    public function accept(Request $request, SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('approve', $quotation);
        $quotation = $this->service->transition($quotation, 'accept', $request->user());
        return ApiResponse::success($quotation, 'Sales quotation accepted successfully');
    }

    public function reject(Request $request, SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('approve', $quotation);
        $quotation = $this->service->transition($quotation, 'reject', $request->user());
        return ApiResponse::success($quotation, 'Sales quotation rejected successfully');
    }

    public function convertToSalesOrder(Request $request, SalesQuotation $quotation): JsonResponse
    {
        Gate::authorize('approve', $quotation);
        $expectedDeliveryDate = $request->input('expected_delivery_date');
        $order = $this->service->convertToSalesOrder($quotation, $expectedDeliveryDate, $request->user());
        return ApiResponse::created($order, 'Sales Order created from quotation successfully');
    }
}
