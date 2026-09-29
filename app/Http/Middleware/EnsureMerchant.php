<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Enums\MerchantStatus;
use App\Exceptions\ApiException;
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
 * Before registration is complete every route here answers
 * REGISTRATION_INCOMPLETE with the step the app should open.
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

        if (! $merchant?->hasCompletedRegistration()) {
            throw ApiException::of(ErrorCode::RegistrationIncomplete, 'Finish the registration steps first.', [
                'registration_step' => $merchant?->registrationStep() ?? 'business',
            ]);
        }

        if ($merchant->status === MerchantStatus::Deleted) {
            throw ApiException::of(ErrorCode::Forbidden, 'This business account has been deleted.');
        }

        $request->setUserResolver(fn (): Merchant => $merchant);

        return $next($request);
    }
}
