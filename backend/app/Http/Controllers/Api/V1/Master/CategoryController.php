<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CategoryRequest;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
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
        $query = Category::query();

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

        $categories = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($categories, 'Categories retrieved successfully');
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());
        return ApiResponse::created($category, 'Category created successfully');
    }

    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success($category, 'Category retrieved successfully');
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());
        return ApiResponse::success($category, 'Category updated successfully');
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return ApiResponse::error('Cannot delete category with active product references. Deactivate it instead.', 403);
        }

        $category->delete();
        return ApiResponse::success(null, 'Category deleted successfully');
    }
}
