<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\EmployeeRequest;
use App\Models\Employee;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:employees.view', only: ['index', 'show']),
            new Middleware('permission:employees.create', only: ['store']),
            new Middleware('permission:employees.update', only: ['update']),
            new Middleware('permission:employees.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['company', 'branch', 'department', 'user']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', $search)
                  ->orWhere('last_name', 'LIKE', $search)
                  ->orWhere('employee_code', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('employment_status', $request->string('status')->value());
        }

        $employees = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($employees, 'Employees retrieved successfully');
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());
        $employee->load(['company', 'branch', 'department', 'user']);
        return ApiResponse::created($employee, 'Employee created successfully');
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['company', 'branch', 'department', 'user']);
        return ApiResponse::success($employee, 'Employee retrieved successfully');
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());
        $employee->load(['company', 'branch', 'department', 'user']);
        return ApiResponse::success($employee, 'Employee updated successfully');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();
        return ApiResponse::success(null, 'Employee deleted successfully');
    }
}
