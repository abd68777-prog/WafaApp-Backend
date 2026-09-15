<?php

use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routed under the /api prefix by bootstrap/app.php. Everything is versioned
| so the separate frontend can be upgraded independently of the backend:
| a v2 group can be added alongside v1 without breaking existing clients.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('ping', fn () => response()->json([
        'message' => 'pong',
        'version' => 'v1',
        'time' => now()->toIso8601String(),
    ]))->name('ping');

    // --- Admin dashboard -------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('auth/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:auth')
            ->name('auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('auth/me', [AdminAuthController::class, 'me'])->name('auth.me');
            Route::post('auth/logout', [AdminAuthController::class, 'logout'])->name('auth.logout');
            Route::post('auth/logout-all', [AdminAuthController::class, 'logoutAll'])->name('auth.logout-all');
        });
    });
});
