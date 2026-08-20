<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseReturnRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class PurchaseReturnController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchase_returns.view',   only: ['index', 'show']),
            new Middleware('permission:purchase_returns.create', only: ['store', 'complete']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseReturn::with('goodsReceipt', 'supplier', 'returnedBy');

        if ($request->filled('goods_receipt_id')) {
            $query->where('goods_receipt_id', $request->integer('goods_receipt_id'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Purchase returns retrieved successfully'
        );
    }

    public function store(PurchaseReturnRequest $request): JsonResponse
    {
        $data = $request->validated();
        $grn  = GoodsReceipt::with('purchaseOrder.supplier', 'warehouse')->findOrFail($data['goods_receipt_id']);

        Gate::authorize('view', $grn);

        $return = $this->service->processReturn($grn, $data, $request->user());
        return ApiResponse::created($return, 'Purchase return processed successfully');
    }

    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        Gate::authorize('view', $purchaseReturn);
        $purchaseReturn->load('goodsReceipt.purchaseOrder', 'supplier', 'returnedBy', 'lines.product', 'lines.goodsReceiptLine');
        return ApiResponse::success($purchaseReturn, 'Purchase return retrieved successfully');
    }

    public function complete(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        Gate::authorize('complete', $purchaseReturn);

        if ($purchaseReturn->status !== 'draft') {
            return ApiResponse::error("Purchase return is already '{$purchaseReturn->status}'.", 422);
        }

        $purchaseReturn->update(['status' => 'completed']);
        return ApiResponse::success($purchaseReturn->fresh(), 'Purchase return completed successfully');
    }
}

