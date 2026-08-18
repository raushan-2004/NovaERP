<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ProductRequest;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller implements HasMiddleware
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
        $query = Product::with(['category', 'brand', 'unit']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('sku', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $products = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($products, 'Products retrieved successfully');
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        $product->load(['category', 'brand', 'unit']);
        return ApiResponse::created($product, 'Product created successfully');
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'brand', 'unit']);
        return ApiResponse::success($product, 'Product retrieved successfully');
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $product->update($request->validated());
        $product->load(['category', 'brand', 'unit']);
        return ApiResponse::success($product, 'Product updated successfully');
    }

    public function destroy(Product $product): JsonResponse
    {
        // Deletion safety rule
        $product->delete();
        return ApiResponse::success(null, 'Product deleted successfully');
    }
}
