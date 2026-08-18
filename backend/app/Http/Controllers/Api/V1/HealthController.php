<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status'  => 'ok',
            'version' => 'v1',
            'app'     => config('app.name'),
        ], 'Service is healthy');
    }
}
