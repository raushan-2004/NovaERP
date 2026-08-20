<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockTransferRequest;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\NumberSeriesService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly NumberSeriesService $numberSeries
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view',   only: ['index', 'show']),
            new Middleware('permission:inventory.adjust', only: ['store', 'complete', 'cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with('fromWarehouse', 'toWarehouse', 'product', 'transferredBy');

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->integer('from_warehouse_id'));
        }
        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->integer('to_warehouse_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25)),
            'Stock transfers retrieved successfully'
        );
    }

    public function store(StockTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        $transfer = DB::transaction(function () use ($data, $request) {
            return StockTransfer::create([
                'transfer_number'   => $this->numberSeries->next('TRF'),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'product_id'        => $data['product_id'],
                'quantity'          => $data['quantity'],
                'status'            => 'draft',
                'transferred_by'    => $request->user()->id,
                'notes'             => $data['notes'] ?? null,
            ]);
        });

        $transfer->load('fromWarehouse', 'toWarehouse', 'product', 'transferredBy');
        return ApiResponse::created($transfer, 'Stock transfer created successfully');
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load('fromWarehouse', 'toWarehouse', 'product', 'transferredBy');
        return ApiResponse::success($stockTransfer, 'Stock transfer retrieved successfully');
    }

    public function complete(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== 'draft') {
            return ApiResponse::error(
                "Cannot transition Stock Transfer from '{$stockTransfer->status}' to 'completed'.",
                422
            );
        }

        $from    = Warehouse::findOrFail($stockTransfer->from_warehouse_id);
        $to      = Warehouse::findOrFail($stockTransfer->to_warehouse_id);
        $product = Product::findOrFail($stockTransfer->product_id);

        $this->inventoryService->transfer($from, $to, $product, (string) $stockTransfer->quantity, $stockTransfer, $request->user());

        $stockTransfer->update([
            'status'         => 'completed',
            'transferred_at' => now(),
        ]);

        $stockTransfer->load('fromWarehouse', 'toWarehouse', 'product', 'transferredBy');
        return ApiResponse::success($stockTransfer, 'Stock transfer completed successfully');
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== 'draft') {
            return ApiResponse::error(
                "Cannot transition Stock Transfer from '{$stockTransfer->status}' to 'cancelled'.",
                422
            );
        }

        $stockTransfer->update(['status' => 'cancelled']);
        $stockTransfer->load('fromWarehouse', 'toWarehouse', 'product', 'transferredBy');
        return ApiResponse::success($stockTransfer, 'Stock transfer cancelled successfully');
    }
}
