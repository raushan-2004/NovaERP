<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\BrandRequest;
use App\Models\Brand;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BrandController extends Controller implements HasMiddleware
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
        $query = Brand::query();

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('code', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $brands = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($brands, 'Brands retrieved successfully');
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());
        return ApiResponse::created($brand, 'Brand created successfully');
    }

    public function show(Brand $brand): JsonResponse
    {
        return ApiResponse::success($brand, 'Brand retrieved successfully');
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());
        return ApiResponse::success($brand, 'Brand updated successfully');
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return ApiResponse::error('Cannot delete brand with active product references. Deactivate it instead.', 403);
        }

        $brand->delete();
        return ApiResponse::success(null, 'Brand deleted successfully');
    }
}
