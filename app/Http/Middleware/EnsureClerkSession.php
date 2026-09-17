<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidClerkTokenException;
use App\Services\Clerk\ClerkSession;
use App\Services\Clerk\ClerkTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a valid Clerk session token and attaches the verified session to
 * the request. It says who the Clerk user is, not what they may do: pair it
 * with `clerk.admin` or `clerk.merchant` for that.
 */
class EnsureClerkSession
{
    public function __construct(private readonly ClerkTokenVerifier $verifier) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (blank($token)) {
            throw new InvalidClerkTokenException('Missing bearer token.');
        }

        $request->attributes->set(ClerkSession::class, $this->verifier->verify($token));

        return $next($request);
    }
}
