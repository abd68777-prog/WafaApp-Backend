<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\StampController as AdminStampController;
use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\CardController as CustomerCardController;
use App\Http\Controllers\Api\V1\Customer\ConfigController as CustomerConfigController;
use App\Http\Controllers\Api\V1\Customer\MeController as CustomerMeController;
use App\Http\Controllers\Api\V1\Customer\PolicyController as CustomerPolicyController;
use App\Http\Controllers\Api\V1\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\V1\Customer\QrController as CustomerQrController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\Api\V1\Merchant\BirthdayController;
use App\Http\Controllers\Api\V1\Merchant\CardController as MerchantCardController;
use App\Http\Controllers\Api\V1\Merchant\LookupController as MerchantLookupController;
use App\Http\Controllers\Api\V1\Merchant\PinController;
use App\Http\Controllers\Api\V1\Merchant\RedemptionController;
use App\Http\Controllers\Api\V1\Merchant\RegistrationController;
use App\Http\Controllers\Api\V1\Merchant\ScanController;
use App\Http\Controllers\Api\V1\Merchant\StampController;
use App\Http\Controllers\Api\V1\NotificationController;
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
| The customer and merchant routes follow Deep Code's API contract
| (1.0.0-draft.1) path for path; every error has the shape
| { "error": { "code", "message", "details" } }.
|
| Two authentication schemes:
| - Customers: phone + OTP, then a Sanctum token scoped to `customer`.
| - Merchants and dashboard users: a Clerk session token on every request.
|
| Access beyond signing in:
| - Customers finish their profile and consent first: `customer.ready`.
| - Merchant tabs behind the PIN use `merchant.pin`.
| - Dashboard actions use `->can(AdminPermission::…->value)`, following the
|   role matrix in AdminRole::permissions().
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('ping', fn () => response()->json([
        'message' => 'pong',
        'version' => 'v1',
        'time' => now()->toIso8601ZuluString(),
    ]))->name('ping');

    // --- Server-to-server ------------------------------------------------
    // Signed by Clerk (Svix); the signature is the authentication.
    Route::post('webhooks/clerk', [ClerkWebhookController::class, 'handle'])->name('webhooks.clerk');

    // --- Customer app (phone + OTP) --------------------------------------
    Route::prefix('customer')->name('customer.')->group(function () {
        Route::get('config', [CustomerConfigController::class, 'show'])->name('config');

        Route::post('auth/otp', [CustomerAuthController::class, 'requestCode'])
            ->middleware('throttle:otp')
            ->name('auth.otp');

        Route::post('auth/verify', [CustomerAuthController::class, 'verify'])
            ->middleware('throttle:otp-verify')
            ->name('auth.verify');

        Route::middleware(['auth:sanctum', 'abilities:customer'])->group(function () {
            // Reachable with an incomplete profile or an outdated consent, so
            // the app can always show the screen that fixes it.
            Route::post('auth/logout', [CustomerAuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [CustomerMeController::class, 'show'])->name('me.show');
            Route::delete('me', [CustomerMeController::class, 'destroy'])->name('me.destroy');
            Route::post('me/profile', [CustomerProfileController::class, 'store'])->name('me.profile');
            Route::post('me/policy-consents', [CustomerPolicyController::class, 'store'])->name('me.policy-consents');

            Route::put('devices', [DeviceController::class, 'update'])->name('devices.update');
            Route::delete('devices/{token}', [DeviceController::class, 'destroy'])
                ->where('token', '.+')
                ->name('devices.destroy');

            Route::middleware('customer.ready')->group(function () {
                Route::patch('me', [CustomerMeController::class, 'update'])->name('me.update');
                Route::get('me/qr', [CustomerQrController::class, 'show'])->name('me.qr');

                Route::get('cards', [CustomerCardController::class, 'index'])->name('cards.index');
                Route::get('cards/{card}', [CustomerCardController::class, 'show'])->whereNumber('card')->name('cards.show');

                Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
                Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
            });
        });
    });

    // --- Merchant app (Clerk) --------------------------------------------
    // The app is distributed outside the stores, so every merchant route also
    // checks the app version and answers with an update screen when it is old.
    Route::prefix('merchant')->name('merchant.')->middleware(['app.version', 'clerk'])->group(function () {
        // Open to any signed-in Clerk user: these drive registration itself.
        Route::get('me', [MerchantAuthController::class, 'me'])->name('me');
        Route::get('lookups', [MerchantLookupController::class, 'index'])->name('lookups');
        Route::post('registration/business', [RegistrationController::class, 'business'])->name('registration.business');
        Route::post('registration/package', [RegistrationController::class, 'package'])->name('registration.package');
        Route::post('registration/pin', [RegistrationController::class, 'pin'])->name('registration.pin');

        // Everything past registration.
        Route::middleware('clerk.merchant')->group(function () {
            Route::post('pin/unlock', [PinController::class, 'unlock'])->name('pin.unlock');
            Route::post('pin/reset', [PinController::class, 'reset'])->name('pin.reset');

            Route::put('devices', [DeviceController::class, 'update'])->name('devices.update');
            Route::delete('devices/{token}', [DeviceController::class, 'destroy'])
                ->where('token', '.+')
                ->name('devices.destroy');

            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');

            // The scan screen belongs to the cashier: no PIN.
            Route::get('cards', [MerchantCardController::class, 'index'])->name('cards.index');
            Route::post('scan/resolve', [ScanController::class, 'resolve'])->name('scan.resolve');
            Route::post('stamps', [StampController::class, 'store'])->name('stamps.store');
            Route::post('redemptions', [RedemptionController::class, 'store'])->name('redemptions.store');

            // The PIN-protected tabs.
            Route::middleware('merchant.pin')->group(function () {
                Route::put('pin', [PinController::class, 'update'])->name('pin.update');

                Route::post('cards', [MerchantCardController::class, 'store'])->name('cards.store');
                Route::post('cards/{card}/suspend', [MerchantCardController::class, 'suspend'])->whereNumber('card')->name('cards.suspend');

                Route::get('customers/birthdays-today', [BirthdayController::class, 'index'])->name('customers.birthdays-today');
                Route::post('customers/{customer}/birthday-greeting', [BirthdayController::class, 'store'])
                    ->whereNumber('customer')
                    ->name('customers.birthday-greeting');
            });
        });
    });

    // --- Admin dashboard (Clerk) -----------------------------------------
    // Outside the contract's first version; same error shape.
    Route::prefix('admin')->name('admin.')->middleware(['clerk', 'clerk.admin'])->group(function () {
        Route::get('auth/me', [AdminAuthController::class, 'me'])->name('auth.me');

        Route::middleware('can:'.AdminPermission::ManageAdminAccounts->value)->group(function () {
            Route::get('admin-users', [AdminUserController::class, 'index'])->name('admin-users.index');
            Route::post('admin-users', [AdminUserController::class, 'store'])->name('admin-users.store');
            Route::patch('admin-users/{adminUser}', [AdminUserController::class, 'update'])->name('admin-users.update');
            Route::delete('admin-users/{adminUser}', [AdminUserController::class, 'destroy'])->name('admin-users.destroy');
        });

        Route::post('stamps/{stamp}/cancel', [AdminStampController::class, 'cancel'])
            ->middleware('can:'.AdminPermission::CancelStamps->value)
            ->name('stamps.cancel');
    });
});
