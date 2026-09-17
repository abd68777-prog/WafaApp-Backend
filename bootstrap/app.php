<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClerkSession;
use App\Http\Middleware\EnsureMerchant;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

            // Merchants and admins: a verified Clerk session, then a role
            // resolved from our own tables.
            'clerk' => EnsureClerkSession::class,
            'clerk.admin' => EnsureAdmin::class,
            'clerk.merchant' => EnsureMerchant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Do not leak model class names to API clients.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Endpoint not found.'], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        // A valid token used on routes it was not issued for. Sanctum's
        // MissingAbilityException is already wrapped in an AccessDeniedHttpException
        // by the time renderers run, so it is matched through the wrapper —
        // other 403s keep their own message.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof MissingAbilityException) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'This token is not allowed to access this resource.'], 403);
            }

            return null;
        });
    })->create();
