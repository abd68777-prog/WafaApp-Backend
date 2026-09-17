<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\BillingCycle;
use App\Enums\MerchantStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\RegisterMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\PlatformSetting;
use App\Services\Clerk\ClerkSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Merchants sign in through Clerk. Signing in only proves who the user is;
 * the business itself is created here, once, from the registration form.
 */
class AuthController extends Controller
{
    private const DEFAULT_TRIAL_DAYS = 14;

    /**
     * The merchant behind the Clerk user, or `registered: false` so the app
     * knows to show the business registration form instead of an error.
     */
    public function me(Request $request): JsonResponse
    {
        $merchant = Merchant::query()
            ->with('package')
            ->where('clerk_user_id', ClerkSession::fromRequest($request)->userId)
            ->first();

        return response()->json([
            'registered' => $merchant !== null,
            'data' => $merchant ? new MerchantResource($merchant) : null,
        ]);
    }

    /**
     * Create the business for the signed-in Clerk user and start its free trial
     * immediately (PRD 4.1).
     */
    public function register(RegisterMerchantRequest $request): JsonResponse
    {
        $clerkUserId = ClerkSession::fromRequest($request)->userId;

        if (Merchant::query()->where('clerk_user_id', $clerkUserId)->exists()) {
            return $this->alreadyRegistered();
        }

        $package = Package::query()->where('code', $request->string('package_code')->value())->firstOrFail();
        $trialDays = (int) (PlatformSetting::query()->where('key', 'trial_days')->value('value') ?? self::DEFAULT_TRIAL_DAYS);

        try {
            $merchant = DB::transaction(function () use ($request, $clerkUserId, $package, $trialDays): Merchant {
                $merchant = new Merchant($request->safe()->only('business_name', 'owner_name', 'phone', 'email', 'city', 'address'));

                $merchant->forceFill([
                    'clerk_user_id' => $clerkUserId,
                    'package_id' => $package->id,
                    'status' => MerchantStatus::Trial,
                    'trial_ends_at' => now()->addDays($trialDays),
                ])->save();

                // The trial is the first subscription period, so the audit trail
                // of plan changes starts on day one.
                $merchant->subscriptions()->create([
                    'package_id' => $package->id,
                    'billing_cycle' => BillingCycle::Trial,
                    'price_usd' => 0,
                    'starts_at' => now(),
                    'ends_at' => $merchant->trial_ends_at,
                    'status' => SubscriptionStatus::Active,
                ]);

                return $merchant;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Two registration requests for the same Clerk user raced past the
            // check above; the unique index is the final guard.
            if (Merchant::query()->where('clerk_user_id', $clerkUserId)->exists()) {
                return $this->alreadyRegistered();
            }

            throw $exception;
        }

        // Refreshed so columns filled by database defaults (e.g.
        // birthday_gift_enabled) come back with their stored values, not null.
        return (new MerchantResource($merchant->refresh()->load('package')))
            ->response()
            ->setStatusCode(201);
    }

    private function alreadyRegistered(): JsonResponse
    {
        return response()->json(['message' => 'This account already has a registered business.'], 409);
    }
}
