<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'permission:roles.view',
        ];
    }

    /**
     * Get a flat list of all system permissions.
     * Permissions are immutable system-defined values (Read-only API).
     */
    public function __invoke(): JsonResponse
    {
        $permissions = Permission::all();
        return ApiResponse::success($permissions, 'Permissions retrieved successfully');
    }
}
