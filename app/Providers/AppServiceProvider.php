<?php

namespace App\Providers;

use App\Enums\AdminPermission;
use App\Models\AdminUser;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Stamp;
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
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

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
        $this->configureAuthentication();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * A customer's token stops working once it has gone unused for
     * `sanctum.customer_idle_days`; Sanctum renews `last_used_at` on every
     * request it accepts. Refused tokens get 401 like any invalid one.
     */
    protected function configureAuthentication(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && ($token->tokenable_type !== (new Customer)->getMorphClass()
                    || ($token->last_used_at ?? $token->created_at)->isAfter(Customer::tokenIdleCutoff())),
        );
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
     * columns (Sanctum tokens, notifications, device tokens, audit subjects),
     * so renaming a model never orphans existing rows.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'admin' => AdminUser::class,
            'merchant' => Merchant::class,
            'customer' => Customer::class,
            'stamp' => Stamp::class,
            'payment' => Payment::class,
            'package' => Package::class,
            'card' => Card::class,
        ]);
    }

    protected function configureRateLimiting(): void
    {
        // General API traffic: generous, keyed per authenticated user.
        // Runs before authentication, so the caller is known only by the token
        // it sends. Keying by IP alone would make every customer and shop
        // behind one mobile carrier's shared address share one budget.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by(filled($request->bearerToken())
                ? 'token:'.hash('sha256', (string) $request->bearerToken())
                : 'ip:'.$request->ip()));

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
