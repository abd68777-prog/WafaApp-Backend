<?php

namespace App\Providers;

use App\Enums\AdminPermission;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Merchant;
use App\Services\Clerk\ClerkBackendApi;
use App\Services\Clerk\ClerkTokenVerifier;
use App\Services\Clerk\ClerkWebhookSignature;
use App\Services\Otp\LightOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpSender::class, function (): OtpSender {
            return match (config('otp.driver')) {
                'lightotp' => new LightOtpSender(
                    (string) config('services.lightotp.key'),
                    rtrim((string) config('services.lightotp.base_url'), '/'),
                    (string) config('services.lightotp.language'),
                    (int) config('services.lightotp.timeout'),
                ),
                default => new LogOtpSender,
            };
        });

        $this->app->singleton(ClerkTokenVerifier::class, fn (): ClerkTokenVerifier => new ClerkTokenVerifier(
            config('services.clerk.jwt_key'),
            config('services.clerk.authorized_parties', []),
            (int) config('services.clerk.clock_skew'),
        ));

        $this->app->bind(ClerkWebhookSignature::class, fn (): ClerkWebhookSignature => new ClerkWebhookSignature(
            config('services.clerk.webhook_secret'),
        ));

        $this->app->bind(ClerkBackendApi::class, fn (): ClerkBackendApi => new ClerkBackendApi(
            (string) config('services.clerk.secret_key'),
            rtrim((string) config('services.clerk.api_url'), '/'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Each dashboard permission is a gate of the same name. Only an admin
     * account can pass one: a merchant or customer reaching a gate is refused.
     */
    protected function configureAuthorization(): void
    {
        foreach (AdminPermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (Authenticatable $user): bool => $user instanceof AdminUser && $user->hasPermission($permission),
            );
        }
    }

    /**
     * Fail loudly on lazy loading and mass-assignment mistakes while developing.
     *
     * The morph map stores short aliases instead of class names in polymorphic
     * columns (Sanctum tokens, notifications, device tokens), so renaming a
     * model never orphans existing rows.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'admin' => AdminUser::class,
            'merchant' => Merchant::class,
            'customer' => Customer::class,
        ]);
    }

    protected function configureRateLimiting(): void
    {
        // General API traffic: generous, keyed per authenticated user.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Sending a code costs real money, so it is capped per number and per
        // source. The per-request cooldown lives in OtpService.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perHour(5)->by('otp-phone:'.$this->phoneKey($request)),
            Limit::perHour(20)->by('otp-ip:'.$request->ip()),
        ]);

        // Guessing a code is cheap, so the ceiling is per minute; the code also
        // dies after `otp.max_attempts` wrong tries.
        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-verify-phone:'.$this->phoneKey($request)),
            Limit::perMinute(30)->by('otp-verify-ip:'.$request->ip()),
        ]);

        // A four digit PIN is guessable, so unlocking is throttled per shop.
        RateLimiter::for('pin', fn (Request $request) => Limit::perMinute(5)
            ->by('pin:'.($request->user()?->id ?: $request->ip())));
    }

    /**
     * Rate limits key on the normalized number so `0947…` and `+963947…`
     * cannot be used as two separate budgets for the same phone.
     */
    private function phoneKey(Request $request): string
    {
        return PhoneNumber::normalize((string) $request->input('phone')) ?? (string) $request->ip();
    }
}
