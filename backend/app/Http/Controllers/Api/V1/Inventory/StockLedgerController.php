<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockLedgerEntry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockLedgerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view', only: ['index']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockLedgerEntry::with('product', 'warehouse', 'createdBy')
            ->orderBy('occurred_at', 'desc');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }
        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->string('movement_type')->value());
        }
        if ($request->filled('occurred_at_from')) {
            $query->where('occurred_at', '>=', $request->string('occurred_at_from')->value());
        }
        if ($request->filled('occurred_at_to')) {
            $query->where('occurred_at', '<=', $request->string('occurred_at_to')->value());
        }

        return ApiResponse::paginated(
            $query->paginate($request->integer('per_page', 25)),
            'Stock ledger entries retrieved successfully'
        );
    }
}
