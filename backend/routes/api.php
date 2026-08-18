<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NovaERP API Routes — Version 1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 by the framework (see bootstrap/app.php).
| Follow conventions defined in docs/API_CONTRACT.md.
|
*/

Route::prefix('v1')->group(function () {

    // Public endpoints
    Route::get('/health', HealthController::class);

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

});
