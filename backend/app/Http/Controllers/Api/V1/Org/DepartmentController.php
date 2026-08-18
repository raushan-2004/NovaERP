<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\DepartmentRequest;
use App\Models\Department;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DepartmentController extends Controller implements HasMiddleware
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
        $query = Department::with(['company', 'branch']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('department_code', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $departments = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($departments, 'Departments retrieved successfully');
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());
        $department->load(['company', 'branch']);
        return ApiResponse::created($department, 'Department created successfully');
    }

    public function show(Department $department): JsonResponse
    {
        $department->load(['company', 'branch', 'employees']);
        return ApiResponse::success($department, 'Department retrieved successfully');
    }

    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());
        $department->load(['company', 'branch']);
        return ApiResponse::success($department, 'Department updated successfully');
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->exists()) {
            return ApiResponse::error('Cannot delete department with active employee references. Deactivate it instead.', 403);
        }

        $department->delete();
        return ApiResponse::success(null, 'Department deleted successfully');
    }
}
