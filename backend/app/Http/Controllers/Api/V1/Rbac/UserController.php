<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\UserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index', 'show']),
            new Middleware('permission:users.create', only: ['store']),
            new Middleware('permission:users.update', only: ['update']),
            new Middleware('permission:users.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'employee']);

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search);
            });
        }

        $users = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($users, 'Users retrieved successfully');
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        if ($request->has('roles')) {
            $user->roles()->sync($request->input('roles'));
        }

        $user->load('roles');

        return ApiResponse::created($user, 'User created successfully');
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'employee']);
        return ApiResponse::success($user, 'User retrieved successfully');
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        if ($request->has('roles')) {
            // Prevent stripping the last Super Admin of their role
            if ($user->hasRole('Super Admin') && ! in_array(
                (int) \App\Models\Role::where('name', 'Super Admin')->first()?->id,
                array_map('intval', $request->input('roles')),
                true
            )) {
                $superAdminsCount = User::whereHas('roles', function ($q) {
                    $q->where('name', 'Super Admin');
                })->count();

                if ($superAdminsCount <= 1) {
                    return ApiResponse::error('The system must have at least one active Super Admin user.', 403);
                }
            }

            $user->roles()->sync($request->input('roles'));
        }

        $user->load('roles');

        return ApiResponse::success($user, 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->hasRole('Super Admin')) {
            $superAdminsCount = User::whereHas('roles', function ($q) {
                $q->where('name', 'Super Admin');
            })->count();

            if ($superAdminsCount <= 1) {
                return ApiResponse::error('The only active Super Admin user cannot be deleted.', 403);
            }
        }

        $user->delete();

        return ApiResponse::success(null, 'User deleted successfully');
    }
}
