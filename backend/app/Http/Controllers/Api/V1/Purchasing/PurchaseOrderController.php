<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchase_orders.view',    only: ['index', 'show']),
            new Middleware('permission:purchase_orders.create',  only: ['store']),
            new Middleware('permission:purchase_orders.update',  only: ['update', 'submit', 'send']),
            new Middleware('permission:purchase_orders.delete',  only: ['destroy']),
            new Middleware('permission:purchase_orders.approve', only: ['approve', 'close', 'cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with('company', 'branch', 'supplier', 'createdBy');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Purchase orders retrieved successfully'
        );
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $po = $this->service->create($request->validated(), $request->user());
        return ApiResponse::created($po, 'Purchase order created successfully');
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('view', $purchaseOrder);
        $purchaseOrder->load('company', 'branch', 'supplier', 'createdBy', 'lines.product', 'lines.unit', 'goodsReceipts');
        return ApiResponse::success($purchaseOrder, 'Purchase order retrieved successfully');
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('update', $purchaseOrder);
        $purchaseOrder->update([
            'expected_delivery_date' => $request->validated()['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
            'notes'                  => $request->validated()['notes'] ?? $purchaseOrder->notes,
        ]);
        return ApiResponse::success($purchaseOrder->fresh(), 'Purchase order updated successfully');
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        $purchaseOrder->delete();
        return ApiResponse::success(null, 'Purchase order deleted successfully');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('update', $purchaseOrder);
        return ApiResponse::success($this->service->submit($purchaseOrder, $request->user()), 'Purchase order submitted successfully');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('approve', $purchaseOrder);
        return ApiResponse::success($this->service->approve($purchaseOrder, $request->user()), 'Purchase order approved successfully');
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('send', $purchaseOrder);
        return ApiResponse::success($this->service->send($purchaseOrder, $request->user()), 'Purchase order sent to supplier successfully');
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('approve', $purchaseOrder);
        return ApiResponse::success($this->service->close($purchaseOrder, $request->user()), 'Purchase order closed successfully');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('approve', $purchaseOrder);
        return ApiResponse::success($this->service->cancel($purchaseOrder, $request->user()), 'Purchase order cancelled successfully');
    }
}

