<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\BranchRequest;
use App\Models\Branch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BranchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:organization.view', only: ['index', 'show']),
            new Middleware('permission:organization.create', only: ['store']),
            new Middleware('permission:organization.update', only: ['update']),
            new Middleware('permission:organization.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Branch::with('company');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('branch_code', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $branches = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($branches, 'Branches retrieved successfully');
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());
        $branch->load('company');
        return ApiResponse::created($branch, 'Branch created successfully');
    }

    public function show(Branch $branch): JsonResponse
    {
        $branch->load(['company', 'departments', 'warehouses']);
        return ApiResponse::success($branch, 'Branch retrieved successfully');
    }

    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());
        $branch->load('company');
        return ApiResponse::success($branch, 'Branch updated successfully');
    }

    public function destroy(Branch $branch): JsonResponse
    {
        if ($branch->departments()->exists() || $branch->warehouses()->exists()) {
            return ApiResponse::error('Cannot delete branch with active department or warehouse references. Deactivate it instead.', 403);
        }

        $branch->delete();
        return ApiResponse::success(null, 'Branch deleted successfully');
    }
}
