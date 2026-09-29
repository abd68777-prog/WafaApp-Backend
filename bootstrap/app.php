<?php

use App\Exceptions\ApiErrorRenderer;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClerkSession;
use App\Http\Middleware\EnsureCustomerReady;
use App\Http\Middleware\EnsureMerchant;
use App\Http\Middleware\EnsurePinUnlocked;
use App\Http\Middleware\EnsureSupportedAppVersion;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every API request is answered as JSON, even when the client
        // forgets to send an Accept header.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->api(append: [
            ThrottleRequests::using('api'),
        ]);

        $middleware->alias([
            // Customer Sanctum tokens are scoped by ability.
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,

            // A customer must finish the profile and agree to the current
            // privacy policy before using the app.
            'customer.ready' => EnsureCustomerReady::class,

            // Merchants and dashboard users: a verified Clerk session, then a
            // role resolved from our own tables.
            'clerk' => EnsureClerkSession::class,
            'clerk.admin' => EnsureAdmin::class,
            'clerk.merchant' => EnsureMerchant::class,

            // The protected tabs of the merchant app need the PIN, proved by
            // the token `pin/unlock` returns.
            'merchant.pin' => EnsurePinUnlocked::class,

            // The merchant app is not updated by a store, so old builds are
            // refused with a specific code.
            'app.version' => EnsureSupportedAppVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One error shape for every API response (API contract §1.3):
        // { "error": { "code", "message", "details" } }.
        $exceptions->render((new ApiErrorRenderer)(...));
    })->create();
