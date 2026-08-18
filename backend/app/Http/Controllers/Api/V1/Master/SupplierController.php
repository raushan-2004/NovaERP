<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SupplierRequest;
use App\Models\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:suppliers.view', only: ['index', 'show']),
            new Middleware('permission:suppliers.create', only: ['store']),
            new Middleware('permission:suppliers.update', only: ['update']),
            new Middleware('permission:suppliers.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Supplier::with('company');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('supplier_code', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $suppliers = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($suppliers, 'Suppliers retrieved successfully');
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());
        $supplier->load('company');
        return ApiResponse::created($supplier, 'Supplier created successfully');
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load('company');
        return ApiResponse::success($supplier, 'Supplier retrieved successfully');
    }

    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());
        $supplier->load('company');
        return ApiResponse::success($supplier, 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return ApiResponse::success(null, 'Supplier deleted successfully');
    }
}
