<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesOrderRequest;
use App\Models\SalesOrder;
use App\Services\Sales\SalesOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class SalesOrderController extends Controller implements HasMiddleware
{
    public function __construct(protected SalesOrderService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales_orders.view',    only: ['index', 'show']),
            new Middleware('permission:sales_orders.create',  only: ['store']),
            new Middleware('permission:sales_orders.update',  only: ['update', 'submit', 'cancel']),
            new Middleware('permission:sales_orders.approve', only: ['approve']),
            new Middleware('permission:sales_orders.delete',  only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SalesOrder::with('customer', 'company', 'branch', 'creator', 'lines')
            ->where('company_id', $user->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Sales orders retrieved successfully'
        );
    }

    public function store(SalesOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $customer = \App\Models\Customer::findOrFail($data['customer_id']);
        if ($customer->company_id !== $user->company_id) {
            return response()->json(['message' => 'Forbidden customer company scope mismatch'], 403);
        }

        // Enforce user's company
        $data['company_id'] = $user->company_id;

        $order = $this->service->create($data, $data['lines'], $user);
        return ApiResponse::created($order, 'Sales order created successfully');
    }

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('view', $salesOrder);
        $salesOrder->load('customer', 'company', 'branch', 'creator', 'lines.product', 'lines.unit');
        return ApiResponse::success($salesOrder, 'Sales order retrieved successfully');
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('update', $salesOrder);

        $data = $request->validated();
        $salesOrder->update([
            'expected_delivery_date' => $data['expected_delivery_date'] ?? $salesOrder->expected_delivery_date,
            'notes' => $data['notes'] ?? $salesOrder->notes,
        ]);

        return ApiResponse::success($salesOrder->fresh(), 'Sales order updated successfully');
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('delete', $salesOrder);
        $salesOrder->delete();
        return ApiResponse::success(null, 'Sales order deleted successfully');
    }

    public function submit(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('update', $salesOrder);
        $salesOrder = $this->service->transition($salesOrder, 'submit', $request->user());
        return ApiResponse::success($salesOrder, 'Sales order submitted successfully');
    }

    public function approve(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('approve', $salesOrder);
        $salesOrder = $this->service->transition($salesOrder, 'approve', $request->user());
        return ApiResponse::success($salesOrder, 'Sales order approved successfully');
    }

    public function cancel(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('update', $salesOrder);
        $salesOrder = $this->service->transition($salesOrder, 'cancel', $request->user());
        return ApiResponse::success($salesOrder, 'Sales order cancelled successfully');
    }
}
