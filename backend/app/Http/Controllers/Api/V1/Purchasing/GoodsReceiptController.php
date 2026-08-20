<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\GoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class GoodsReceiptController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:goods_receipts.view',   only: ['index', 'show']),
            new Middleware('permission:goods_receipts.create', only: ['store', 'complete']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with('purchaseOrder', 'warehouse', 'receivedBy');

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->integer('purchase_order_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Goods receipts retrieved successfully'
        );
    }

    public function store(GoodsReceiptRequest $request): JsonResponse
    {
        $data = $request->validated();
        $po   = PurchaseOrder::findOrFail($data['purchase_order_id']);

        $grn = $this->service->receiveGoods($po, $data, $request->user());
        return ApiResponse::created($grn, 'Goods receipt created and completed successfully');
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        Gate::authorize('view', $goodsReceipt);
        $goodsReceipt->load('purchaseOrder.supplier', 'warehouse', 'receivedBy', 'lines.product', 'lines.purchaseOrderLine');
        return ApiResponse::success($goodsReceipt, 'Goods receipt retrieved successfully');
    }

    public function complete(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        Gate::authorize('complete', $goodsReceipt);

        if ($goodsReceipt->status !== 'draft') {
            return ApiResponse::error("Goods receipt is already '{$goodsReceipt->status}'.", 422);
        }

        $goodsReceipt->update(['status' => 'completed']);
        return ApiResponse::success($goodsReceipt->fresh(), 'Goods receipt completed successfully');
    }
}

