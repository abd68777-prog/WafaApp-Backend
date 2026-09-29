<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer app is usable only once the customer entered their name and
 * birthdate and agreed to the privacy policy version now in force (contract
 * §4.2). Account, config, consent, sign-out and account deletion stay outside
 * this middleware, so the app can always reach the screen that fixes it.
 */
class EnsureCustomerReady
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Customer $customer */
        $customer = $request->user();

        if (! $customer->hasConsentedToCurrentPolicy()) {
            throw ApiException::of(ErrorCode::PolicyConsentRequired, 'Agree to the current privacy policy first.', [
                'current_version' => Setting::currentPolicyVersion(),
            ]);
        }

        if (! $customer->hasCompleteProfile()) {
            throw ApiException::of(ErrorCode::ProfileIncomplete, 'Enter your name and date of birth first.');
        }

        return $next($request);
    }
}
