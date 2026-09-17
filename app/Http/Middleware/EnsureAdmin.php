<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\Clerk\ClerkSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin access comes from our `admins` table, not from having a Clerk account:
 * merchants sign in to the same Clerk application, so a valid Clerk user who
 * is not linked to an admin row is refused.
 */
class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Admin::query()
            ->where('clerk_user_id', ClerkSession::fromRequest($request)->userId)
            ->first();

        if (! $admin) {
            return response()->json(['message' => 'This account does not have admin access.'], 403);
        }

        $request->setUserResolver(fn (): Admin => $admin);

        return $next($request);
    }
}
