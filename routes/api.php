<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AppSettingsController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BillingSettingsController;
use App\Http\Controllers\Api\V1\Admin\BusinessTypeController;
use App\Http\Controllers\Api\V1\Admin\CardController as AdminCardController;
use App\Http\Controllers\Api\V1\Admin\CardCycleController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\IconController;
use App\Http\Controllers\Api\V1\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Api\V1\Admin\MerchantSuspensionController;
use App\Http\Controllers\Api\V1\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\StampController as AdminStampController;
use App\Http\Controllers\Api\V1\Admin\TrialExtensionController;
use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\CardController as CustomerCardController;
use App\Http\Controllers\Api\V1\Customer\ConfigController as CustomerConfigController;
use App\Http\Controllers\Api\V1\Customer\DirectoryController;
use App\Http\Controllers\Api\V1\Customer\MeController as CustomerMeController;
use App\Http\Controllers\Api\V1\Customer\MerchantMuteController;
use App\Http\Controllers\Api\V1\Customer\PolicyController as CustomerPolicyController;
use App\Http\Controllers\Api\V1\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\V1\Customer\QrController as CustomerQrController;
use App\Http\Controllers\Api\V1\Customer\ShopContactController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\Api\V1\Merchant\BirthdayController;
use App\Http\Controllers\Api\V1\Merchant\CampaignController;
use App\Http\Controllers\Api\V1\Merchant\CardController as MerchantCardController;
use App\Http\Controllers\Api\V1\Merchant\CustomerController as MerchantCustomerController;
use App\Http\Controllers\Api\V1\Merchant\LookupController as MerchantLookupController;
use App\Http\Controllers\Api\V1\Merchant\PaymentController as MerchantPaymentController;
use App\Http\Controllers\Api\V1\Merchant\PinController;
use App\Http\Controllers\Api\V1\Merchant\ProfileController;
use App\Http\Controllers\Api\V1\Merchant\RedemptionController;
use App\Http\Controllers\Api\V1\Merchant\RegistrationController;
use App\Http\Controllers\Api\V1\Merchant\ScanController;
use App\Http\Controllers\Api\V1\Merchant\StampController;
use App\Http\Controllers\Api\V1\Merchant\StatsController;
use App\Http\Controllers\Api\V1\Merchant\SubscriptionController;
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

                Route::get('directory', [DirectoryController::class, 'index'])->name('directory');
                Route::get('shop-contacts', [ShopContactController::class, 'index'])->name('shop-contacts');
                Route::get('merchants/{merchant}', [DirectoryController::class, 'show'])->whereNumber('merchant')->name('merchants.show');
                Route::put('merchants/{merchant}/mute', [MerchantMuteController::class, 'update'])->whereNumber('merchant')->name('merchants.mute');
                Route::delete('merchants/{merchant}/mute', [MerchantMuteController::class, 'destroy'])->whereNumber('merchant')->name('merchants.unmute');

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

                Route::get('stats', [StatsController::class, 'show'])->name('stats');
                Route::get('customers', [MerchantCustomerController::class, 'index'])->name('customers.index');
                Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::post('profile/logo', [ProfileController::class, 'logo'])->name('profile.logo');

                Route::get('customers/birthdays-today', [BirthdayController::class, 'index'])->name('customers.birthdays-today');
                Route::post('customers/{customer}/birthday-greeting', [BirthdayController::class, 'store'])
                    ->whereNumber('customer')
                    ->name('customers.birthday-greeting');

                Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
                Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

                Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription');
                Route::get('payments', [MerchantPaymentController::class, 'index'])->name('payments.index');
                Route::post('payments', [MerchantPaymentController::class, 'store'])->name('payments.store');
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

        // Prices and settings belong to the Super Admin; reviewing payments to
        // the payments reviewer. Nobody holds both (requirements §5.1).
        Route::middleware('can:'.AdminPermission::ManagePackages->value)->group(function () {
            Route::get('packages', [AdminPackageController::class, 'index'])->name('packages.index');
            Route::post('packages', [AdminPackageController::class, 'store'])->name('packages.store');
            Route::patch('packages/{package}', [AdminPackageController::class, 'update'])->name('packages.update');
        });

        Route::middleware('can:'.AdminPermission::ManageSettings->value)->group(function () {
            Route::get('settings/billing', [BillingSettingsController::class, 'show'])->name('settings.billing.show');
            Route::patch('settings/billing', [BillingSettingsController::class, 'update'])->name('settings.billing.update');
            Route::get('settings/app', [AppSettingsController::class, 'show'])->name('settings.app.show');
            Route::patch('settings/app', [AppSettingsController::class, 'update'])->name('settings.app.update');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('can:'.AdminPermission::ViewAuditLog->value)
            ->name('audit-logs.index');

        // Shops and customers (requirements §5.3–§5.4). Looking is open to
        // support; each action needs its own permission.
        Route::middleware('can:'.AdminPermission::ViewMerchantsAndCustomers->value)->group(function () {
            Route::get('merchants', [AdminMerchantController::class, 'index'])->name('merchants.index');
            Route::get('merchants/{merchant}', [AdminMerchantController::class, 'show'])->name('merchants.show');
            Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
            Route::get('card-cycles/{cycle}', [CardCycleController::class, 'show'])->name('card-cycles.show');
        });

        Route::patch('merchants/{merchant}', [AdminMerchantController::class, 'update'])
            ->middleware('can:'.AdminPermission::EditBusinessIdentity->value)
            ->name('merchants.update');

        Route::middleware('can:'.AdminPermission::SuspendMerchants->value)->group(function () {
            Route::post('merchants/{merchant}/suspend', [MerchantSuspensionController::class, 'suspend'])->name('merchants.suspend');
            Route::post('merchants/{merchant}/reactivate', [MerchantSuspensionController::class, 'reactivate'])->name('merchants.reactivate');
            Route::post('cards/{card}/suspend', [AdminCardController::class, 'suspend'])->name('cards.suspend');
        });

        Route::patch('customers/{customer}', [AdminCustomerController::class, 'update'])
            ->middleware('can:'.AdminPermission::EditCustomerBirthdate->value)
            ->name('customers.update');

        Route::post('customers/{customer}/reveal-phone', [AdminCustomerController::class, 'revealPhone'])
            ->middleware('can:'.AdminPermission::RevealCustomerPhone->value)
            ->name('customers.reveal-phone');

        Route::middleware('can:'.AdminPermission::ManageLookups->value)->group(function () {
            Route::get('business-types', [BusinessTypeController::class, 'index'])->name('business-types.index');
            Route::post('business-types', [BusinessTypeController::class, 'store'])->name('business-types.store');
            Route::patch('business-types/{businessType}', [BusinessTypeController::class, 'update'])->name('business-types.update');
            Route::get('icons', [IconController::class, 'index'])->name('icons.index');
            Route::post('icons', [IconController::class, 'store'])->name('icons.store');
            Route::patch('icons/{icon}', [IconController::class, 'update'])->name('icons.update');
        });

        Route::middleware('can:'.AdminPermission::ReviewPayments->value)->group(function () {
            Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
            Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
            Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
        });

        Route::post('merchants/{merchant}/trial-extension', [TrialExtensionController::class, 'store'])
            ->middleware('can:'.AdminPermission::GrantExtensions->value)
            ->name('merchants.trial-extension');
    });
});
