<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Services\AuditLogger;
use App\Services\Clerk\ClerkSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard access comes from our `admin_users` table, not from having a Clerk
 * account: merchants sign in to the same Clerk application, so a valid Clerk
 * user who is not linked to an active admin row is refused.
 *
 * A super admin adds an account by name, email and role; it links to a Clerk
 * user the first time someone signs in with that email.
 */
class EnsureAdmin
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = ClerkSession::fromRequest($request);

        $admin = AdminUser::query()->where('clerk_user_id', $session->userId)->first()
            ?? $this->linkOnFirstSignIn($session, $request);

        if (! $admin?->is_active) {
            return response()->json(['message' => 'This account does not have admin access.'], 403);
        }

        // Gates resolve the user through the auth guard, not through the
        // request, so both need the admin for `->can(...)` routes to work.
        Auth::setUser($admin);
        $request->setUserResolver(fn (): AdminUser => $admin);

        return $next($request);
    }

    /**
     * Link the Clerk user to the active, not yet linked account carrying the
     * same email. The email comes from the verified session token, so only the
     * owner of that address can claim the account.
     */
    private function linkOnFirstSignIn(ClerkSession $session, Request $request): ?AdminUser
    {
        if ($session->email === null) {
            return null;
        }

        $admin = AdminUser::query()
            ->whereNull('clerk_user_id')
            ->where('email', Str::lower($session->email))
            ->where('is_active', true)
            ->first();

        if (! $admin) {
            return null;
        }

        // Conditional so two first requests racing each other link once and
        // write one audit entry.
        $linked = AdminUser::query()
            ->whereKey($admin->id)
            ->whereNull('clerk_user_id')
            ->update(['clerk_user_id' => $session->userId]);

        if ($linked === 1) {
            $this->audit->record($admin, 'admin_user.linked', $admin, ipAddress: $request->ip());
        }

        return AdminUser::query()->where('clerk_user_id', $session->userId)->first();
    }
}
