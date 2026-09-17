<?php

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Services\Clerk\ClerkSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a registered merchant who is allowed to use the app.
 *
 * Each refusal carries a machine-readable `code` so the merchant app can route
 * the user: to the registration form, or to a "contact support" screen.
 */
class EnsureMerchant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $merchant = Merchant::query()
            ->where('clerk_user_id', ClerkSession::fromRequest($request)->userId)
            ->first();

        if (! $merchant) {
            return response()->json([
                'message' => 'Complete your business registration first.',
                'code' => 'merchant_not_registered',
            ], 403);
        }

        if ($merchant->status === MerchantStatus::Suspended) {
            return response()->json([
                'message' => 'This business account has been suspended.',
                'code' => 'merchant_suspended',
            ], 403);
        }

        if ($merchant->status === MerchantStatus::Rejected) {
            return response()->json([
                'message' => 'This business account was not approved.',
                'code' => 'merchant_rejected',
            ], 403);
        }

        $request->setUserResolver(fn (): Merchant => $merchant);

        return $next($request);
    }
}
