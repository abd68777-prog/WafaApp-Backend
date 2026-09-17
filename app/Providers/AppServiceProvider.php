<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Merchant;
use App\Services\Clerk\ClerkTokenVerifier;
use App\Services\Otp\LightOtpSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
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
            'admin' => Admin::class,
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
