<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Services\Clerk\ClerkSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Merchants sign in through Clerk. Signing in only proves who the user is;
 * the shop itself is created through the registration steps.
 */
class AuthController extends Controller
{
    /**
     * Where this Clerk user stands: not registered at all, part-way through
     * registration, or done. The app routes on `registration_step`.
     *
     * The app calls this on start, so it also records the visit and keeps the
     * stored email in step with the one the merchant now signs in with.
     */
    public function me(Request $request): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);

        $merchant = Merchant::query()
            ->with(['businessType', 'governorate', 'subscriptionPeriods.package'])
            ->where('clerk_user_id', $session->userId)
            ->first();

        $merchant?->forceFill([
            'last_login_at' => now(),
            'email' => $session->email !== null ? Str::lower($session->email) : $merchant->email,
        ])->save();

        return response()->json([
            'registered' => $merchant !== null,
            'registration_step' => $merchant?->registrationStep() ?? 'business',
            'data' => $merchant ? new MerchantResource($merchant) : null,
        ]);
    }

    /**
     * Stop this device's notifications. Signing out of Clerk itself happens in
     * the app; this only forgets the device token.
     */
    public function logout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['sometimes', 'string', 'max:255'],
        ]);

        $merchant = Merchant::query()
            ->where('clerk_user_id', ClerkSession::fromRequest($request)->userId)
            ->first();

        if ($merchant && isset($validated['device_token'])) {
            $merchant->deviceTokens()->where('token', $validated['device_token'])->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
