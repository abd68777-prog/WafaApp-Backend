<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Merchant;
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
        //
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

        // Credential endpoints: tight, keyed per email + IP to slow brute force.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
