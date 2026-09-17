<?php

use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Merchant\AuthController as MerchantAuthController;
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
| Two authentication schemes:
| - Customers: phone + OTP, then a Sanctum token scoped to `customer`.
| - Merchants and admins: a Clerk session token on every request.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('ping', fn () => response()->json([
        'message' => 'pong',
        'version' => 'v1',
        'time' => now()->toIso8601String(),
    ]))->name('ping');

    // --- Customer app (phone + OTP) --------------------------------------
    Route::prefix('customer')->name('customer.')->group(function () {
        Route::post('auth/otp/request', [CustomerAuthController::class, 'requestCode'])
            ->middleware('throttle:otp')
            ->name('auth.otp.request');

        Route::post('auth/otp/verify', [CustomerAuthController::class, 'verify'])
            ->middleware('throttle:otp-verify')
            ->name('auth.otp.verify');

        Route::middleware(['auth:sanctum', 'abilities:customer'])->group(function () {
            Route::get('auth/me', [CustomerAuthController::class, 'me'])->name('auth.me');
            Route::post('auth/logout', [CustomerAuthController::class, 'logout'])->name('auth.logout');
            Route::post('auth/logout-all', [CustomerAuthController::class, 'logoutAll'])->name('auth.logout-all');
        });
    });

    // --- Merchant app (Clerk) --------------------------------------------
    // Only `clerk` here: these two routes serve a signed-in Clerk user who may
    // not have registered a business yet. Later merchant routes add
    // `clerk.merchant`.
    Route::prefix('merchant')->name('merchant.')->middleware('clerk')->group(function () {
        Route::get('auth/me', [MerchantAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/register', [MerchantAuthController::class, 'register'])->name('auth.register');
    });

    // --- Admin dashboard (Clerk) -----------------------------------------
    Route::prefix('admin')->name('admin.')->middleware(['clerk', 'clerk.admin'])->group(function () {
        Route::get('auth/me', [AdminAuthController::class, 'me'])->name('auth.me');
    });
});
