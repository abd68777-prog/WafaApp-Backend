<?php

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Services\Clerk\ClerkSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a Clerk user who finished registering a shop.
 *
 * It does not judge the subscription: an expired, grace or suspended merchant
 * still opens the app — handing over rewards a customer already earned stays
 * possible in every non-final state. What each status may do is decided by the
 * endpoints, through MerchantStatus.
 *
 * Every refusal carries a machine-readable `code` so the app can route the
 * user to the right screen.
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

        if (! $merchant->hasCompletedRegistration()) {
            return response()->json([
                'message' => 'Finish the registration steps first.',
                'code' => 'registration_incomplete',
                'registration_step' => $merchant->registrationStep(),
            ], 403);
        }

        if ($merchant->status === MerchantStatus::Deleted) {
            return response()->json([
                'message' => 'This business account has been deleted.',
                'code' => 'merchant_deleted',
            ], 403);
        }

        $request->setUserResolver(fn (): Merchant => $merchant);

        return $next($request);
    }
}
