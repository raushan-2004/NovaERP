<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view',   only: ['index', 'show']),
            new Middleware('permission:inventory.adjust', only: ['store']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockAdjustment::with('product', 'warehouse', 'adjustedBy');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Stock adjustments retrieved successfully'
        );
    }

    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        $data      = $request->validated();
        $product   = Product::findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        $adjustment = $this->inventoryService->adjust(
            $product,
            $warehouse,
            (string) $data['adjusted_quantity'],
            $data['reason'],
            $request->user()
        );

        $adjustment->load('product', 'warehouse', 'adjustedBy');
        return ApiResponse::created($adjustment, 'Stock adjustment recorded successfully');
    }

    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $stockAdjustment->load('product', 'warehouse', 'adjustedBy');
        return ApiResponse::success($stockAdjustment, 'Stock adjustment retrieved successfully');
    }
}
