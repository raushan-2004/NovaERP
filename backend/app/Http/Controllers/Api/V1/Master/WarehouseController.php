<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\WarehouseRequest;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WarehouseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouses.view', only: ['index', 'show']),
            new Middleware('permission:warehouses.create', only: ['store']),
            new Middleware('permission:warehouses.update', only: ['update']),
            new Middleware('permission:warehouses.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::with('branch');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('warehouse_code', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $warehouses = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($warehouses, 'Warehouses retrieved successfully');
    }

    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());
        $warehouse->load('branch');
        return ApiResponse::created($warehouse, 'Warehouse created successfully');
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('branch');
        return ApiResponse::success($warehouse, 'Warehouse retrieved successfully');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());
        $warehouse->load('branch');
        return ApiResponse::success($warehouse, 'Warehouse updated successfully');
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();
        return ApiResponse::success(null, 'Warehouse deleted successfully');
    }
}
