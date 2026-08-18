<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized();
        }

        // Super Admin bypasses general permission checks
        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            return ApiResponse::error('You do not have permission to perform this action.', 403);
        }

        return $next($request);
    }
}
