<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UnitRequest;
use App\Models\Unit;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UnitController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.view', only: ['index', 'show']),
            new Middleware('permission:products.create', only: ['store']),
            new Middleware('permission:products.update', only: ['update']),
            new Middleware('permission:products.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Unit::query();

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('abbreviation', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $units = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($units, 'Units retrieved successfully');
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());
        return ApiResponse::created($unit, 'Unit created successfully');
    }

    public function show(Unit $unit): JsonResponse
    {
        return ApiResponse::success($unit, 'Unit retrieved successfully');
    }

    public function update(UnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());
        return ApiResponse::success($unit, 'Unit updated successfully');
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->products()->exists()) {
            return ApiResponse::error('Cannot delete unit with active product references. Deactivate it instead.', 403);
        }

        $unit->delete();
        return ApiResponse::success(null, 'Unit deleted successfully');
    }
}
