<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse — Reusable JSON response builder for NovaERP API.
 *
 * Error response helpers are used by bootstrap/app.php exception rendering.
 * All API responses follow the envelope defined in docs/API_CONTRACT.md.
 */
class ApiResponse
{
    /**
     * 200 OK — successful response with data.
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * 201 Created — resource created successfully.
     */
    public static function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Paginated response — wraps Laravel paginator output.
     */
    public static function paginated(mixed $paginator, string $message = 'Retrieved successfully'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links'   => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Generic error response.
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * 422 Unprocessable Entity — validation failure.
     */
    public static function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * 401 Unauthorized — not authenticated.
     */
    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * 403 Forbidden — not authorized.
     */
    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * 404 Not Found — resource not found.
     */
    public static function notFound(string $message = 'The requested resource was not found.'): JsonResponse
    {
        return self::error($message, 404);
    }
}
