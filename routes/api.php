<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Customer\AccountController as CustomerAccountController;
use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\PolicyController as CustomerPolicyController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\Api\V1\Merchant\PinController;
use App\Http\Controllers\Api\V1\Merchant\RegistrationController;
use App\Http\Controllers\Api\V1\Webhooks\ClerkWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routed under the /api prefix by bootstrap/app.php, and versioned so the
| three frontends can be upgraded independently of the backend.
|
| Two authentication schemes:
| - Customers: phone + OTP, then a Sanctum token scoped to `customer`.
| - Merchants and dashboard users: a Clerk session token on every request.
|
| Access beyond signing in:
| - Merchant tabs behind the PIN use `merchant.pin`.
| - Dashboard actions use `->can(AdminPermission::…->value)`, following the
|   role matrix in AdminRole::permissions().
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('ping', fn () => response()->json([
        'message' => 'pong',
        'version' => 'v1',
        'time' => now()->toIso8601String(),
    ]))->name('ping');

    // --- Lists the apps need before anyone signs in ----------------------
    Route::prefix('lookups')->name('lookups.')->group(function () {
        Route::get('governorates', [LookupController::class, 'governorates'])->name('governorates');
        Route::get('business-types', [LookupController::class, 'businessTypes'])->name('business-types');
        Route::get('icons', [LookupController::class, 'icons'])->name('icons');
        Route::get('packages', [LookupController::class, 'packages'])->name('packages');
    });

    Route::get('policy', [LookupController::class, 'policy'])->name('policy');

    // --- Server-to-server ------------------------------------------------
    // Signed by Clerk (Svix); the signature is the authentication.
    Route::post('webhooks/clerk', [ClerkWebhookController::class, 'handle'])->name('webhooks.clerk');

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

            Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
            Route::post('policy/accept', [CustomerPolicyController::class, 'accept'])->name('policy.accept');
            Route::delete('account', [CustomerAccountController::class, 'destroy'])->name('account.destroy');
        });
    });

    // --- Merchant app (Clerk) --------------------------------------------
    // The app is distributed outside the stores, so every merchant route also
    // checks the app version and answers with an update screen when it is old.
    Route::prefix('merchant')->name('merchant.')->middleware(['app.version', 'clerk'])->group(function () {
        // Open to any signed-in Clerk user: these drive registration itself.
        Route::get('auth/me', [MerchantAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [MerchantAuthController::class, 'logout'])->name('auth.logout');
        Route::post('registration/business', [RegistrationController::class, 'business'])->name('registration.business');
        Route::post('registration/package', [RegistrationController::class, 'package'])->name('registration.package');
        Route::post('registration/pin', [RegistrationController::class, 'pin'])->name('registration.pin');

        // Everything past registration.
        Route::middleware('clerk.merchant')->group(function () {
            Route::post('pin/verify', [PinController::class, 'verify'])
                ->middleware('throttle:pin')
                ->name('pin.verify');

            // Needs a fresh Clerk sign-in instead of the PIN, so it also
            // covers a forgotten PIN.
            Route::put('pin', [PinController::class, 'update'])->name('pin.update');

            Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');

            // The PIN-protected tabs (statistics, customers, campaigns, cards,
            // subscription, settings) go under `merchant.pin` as they are built.
        });
    });

    // --- Admin dashboard (Clerk) -----------------------------------------
    Route::prefix('admin')->name('admin.')->middleware(['clerk', 'clerk.admin'])->group(function () {
        Route::get('auth/me', [AdminAuthController::class, 'me'])->name('auth.me');

        Route::middleware('can:'.AdminPermission::ManageAdminAccounts->value)->group(function () {
            Route::get('admin-users', [AdminUserController::class, 'index'])->name('admin-users.index');
            Route::post('admin-users', [AdminUserController::class, 'store'])->name('admin-users.store');
            Route::patch('admin-users/{adminUser}', [AdminUserController::class, 'update'])->name('admin-users.update');
            Route::delete('admin-users/{adminUser}', [AdminUserController::class, 'destroy'])->name('admin-users.destroy');
        });
    });
});
