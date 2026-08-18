<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Rbac\RoleController;
use App\Http\Controllers\Api\V1\Rbac\PermissionController;
use App\Http\Controllers\Api\V1\Rbac\UserController;
use App\Http\Controllers\Api\V1\Org\CompanyController;
use App\Http\Controllers\Api\V1\Org\BranchController;
use App\Http\Controllers\Api\V1\Org\DepartmentController;
use App\Http\Controllers\Api\V1\Org\EmployeeController;
use App\Http\Controllers\Api\V1\Master\CategoryController;
use App\Http\Controllers\Api\V1\Master\BrandController;
use App\Http\Controllers\Api\V1\Master\UnitController;
use App\Http\Controllers\Api\V1\Master\ProductController;
use App\Http\Controllers\Api\V1\Master\CustomerController;
use App\Http\Controllers\Api\V1\Master\SupplierController;
use App\Http\Controllers\Api\V1\Master\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NovaERP API Routes — Version 1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 by the framework.
| Follow conventions defined in docs/API_CONTRACT.md.
|
*/

Route::prefix('v1')->group(function () {

    // Public endpoints
    Route::get('/health', HealthController::class);

    // Authentication & Core Session management
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // Protected ERP Resource Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // RBAC Management
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', PermissionController::class); // Read-only permissions flat list
        Route::apiResource('users', UserController::class);

        // Organization Management
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('branches', BranchController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('employees', EmployeeController::class);

        // Catalog Master Data
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('products', ProductController::class);

        // Partner / Site Master Data
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('warehouses', WarehouseController::class);
    });

});
