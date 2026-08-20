<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\WarehouseLocationRequest;
use App\Models\WarehouseLocation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WarehouseLocationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view',   only: ['index', 'show']),
            new Middleware('permission:inventory.adjust', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = WarehouseLocation::with('warehouse');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }
        if ($request->filled('search')) {
            $s = '%' . $request->string('search')->value() . '%';
            $query->where(fn($q) => $q->where('name', 'LIKE', $s)->orWhere('code', 'LIKE', $s));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return ApiResponse::paginated(
            $query->paginate($request->integer('per_page', 25)),
            'Warehouse locations retrieved successfully'
        );
    }

    public function store(WarehouseLocationRequest $request): JsonResponse
    {
        $location = WarehouseLocation::create($request->validated());
        $location->load('warehouse');
        return ApiResponse::created($location, 'Warehouse location created successfully');
    }

    public function show(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $warehouseLocation->load('warehouse');
        return ApiResponse::success($warehouseLocation, 'Warehouse location retrieved successfully');
    }

    public function update(WarehouseLocationRequest $request, WarehouseLocation $warehouseLocation): JsonResponse
    {
        $warehouseLocation->update($request->validated());
        $warehouseLocation->load('warehouse');
        return ApiResponse::success($warehouseLocation, 'Warehouse location updated successfully');
    }

    public function destroy(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $warehouseLocation->delete();
        return ApiResponse::success(null, 'Warehouse location deleted successfully');
    }
}
