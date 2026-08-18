<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\CompanyRequest;
use App\Models\Company;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompanyController extends Controller implements HasMiddleware
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
        $query = Company::query();

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where('name', 'LIKE', $search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $companies = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($companies, 'Companies retrieved successfully');
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());
        return ApiResponse::created($company, 'Company created successfully');
    }

    public function show(Company $company): JsonResponse
    {
        $company->load(['branches', 'departments']);
        return ApiResponse::success($company, 'Company retrieved successfully');
    }

    public function update(CompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());
        return ApiResponse::success($company, 'Company updated successfully');
    }

    public function destroy(Company $company): JsonResponse
    {
        // Deletion safety: prevent deleting if there are active branch references
        if ($company->branches()->exists()) {
            return ApiResponse::error('Cannot delete company with active branch references. Deactivate it instead.', 403);
        }

        $company->delete();
        return ApiResponse::success(null, 'Company deleted successfully');
    }
}
