<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email:      $request->string('email')->value(),
            password:   $request->string('password')->value(),
            deviceName: $request->header('User-Agent', 'api'),
        );

        $userData = $this->formatUserResponse($result['user']);

        return ApiResponse::success([
            'token' => $result['token'],
            'user'  => $userData,
        ], 'Logged in successfully');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $userData = $this->formatUserResponse($request->user());

        return ApiResponse::success($userData, 'Authenticated user retrieved');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(null, 'Logged out successfully');
    }

    /**
     * Formats user response including scoped roles, permissions and company/branch context.
     * Uses loadMissing to prevent N+1 queries.
     */
    private function formatUserResponse($user): array
    {
        $user->loadMissing([
            'roles.permissions',
            'employee.company',
            'employee.branch',
            'employee.department',
        ]);

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'roles'       => $user->roles->pluck('name')->toArray(),
            'permissions' => $user->getAllPermissions(),
            'employee'    => $user->employee ? [
                'id'            => $user->employee->id,
                'employee_code' => $user->employee->employee_code,
                'first_name'    => $user->employee->first_name,
                'last_name'     => $user->employee->last_name,
                'designation'   => $user->employee->designation,
                'company'       => $user->employee->company ? [
                    'id'   => $user->employee->company->id,
                    'name' => $user->employee->company->name,
                ] : null,
                'branch'        => $user->employee->branch ? [
                    'id'   => $user->employee->branch->id,
                    'name' => $user->employee->branch->name,
                ] : null,
                'department'    => $user->employee->department ? [
                    'id'   => $user->employee->department->id,
                    'name' => $user->employee->department->name,
                ] : null,
            ] : null,
        ];
    }
}
