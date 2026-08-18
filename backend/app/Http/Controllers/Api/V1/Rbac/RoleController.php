<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\RoleRequest;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index', 'show']),
            new Middleware('permission:roles.create', only: ['store']),
            new Middleware('permission:roles.update', only: ['update']),
            new Middleware('permission:roles.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions');

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('description', 'LIKE', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $roles = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($roles, 'Roles retrieved successfully');
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = Role::create($request->only(['name', 'description', 'status']));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions'));
        }

        if ($request->has('users')) {
            $role->users()->sync($request->input('users'));
        }

        $role->load('permissions');

        return ApiResponse::created($role, 'Role created successfully');
    }

    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users']);
        return ApiResponse::success($role, 'Role retrieved successfully');
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        if ($role->name === 'Super Admin' && $request->input('name') !== 'Super Admin') {
            return ApiResponse::error('The Super Admin role name cannot be modified.', 403);
        }

        $role->update($request->only(['name', 'description', 'status']));

        if ($request->has('permissions')) {
            // Super Admin must not lose system permission capabilities
            if ($role->name === 'Super Admin') {
                return ApiResponse::error('Permissions for Super Admin role cannot be modified.', 403);
            }
            $role->permissions()->sync($request->input('permissions'));
        }

        if ($request->has('users')) {
            $role->users()->sync($request->input('users'));
        }

        $role->load('permissions');

        return ApiResponse::success($role, 'Role updated successfully');
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'Super Admin') {
            return ApiResponse::error('The Super Admin role cannot be deleted.', 403);
        }

        $role->delete();

        return ApiResponse::success(null, 'Role deleted successfully');
    }
}
