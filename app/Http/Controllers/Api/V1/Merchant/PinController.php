<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\SetPinRequest;
use App\Http\Requests\Api\V1\Merchant\VerifyPinRequest;
use App\Models\Merchant;
use App\Services\Clerk\ClerkSession;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The PIN that separates the owner from the cashier on a shared account.
 */
class PinController extends Controller
{
    /**
     * How recently the owner must have proved their identity to Clerk before
     * setting a new PIN.
     */
    private const REVERIFICATION_MINUTES = 10;

    public function __construct(private readonly PinUnlockToken $pinUnlockToken) {}

    /**
     * Check the PIN and hand back the token that opens the protected tabs for
     * the rest of the app session.
     */
    public function verify(VerifyPinRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        if ($merchant->pin_hash === null || ! Hash::check($request->string('pin')->value(), $merchant->pin_hash)) {
            throw ValidationException::withMessages([
                'pin' => ['This PIN is not correct.'],
            ]);
        }

        $unlock = $this->pinUnlockToken->issue($merchant);

        return response()->json([
            'message' => 'PIN accepted.',
            'pin_token' => $unlock['token'],
            'expires_at' => $unlock['expires_at']->toIso8601String(),
        ]);
    }

    /**
     * Set a new PIN, which also covers a forgotten one: the old PIN is not
     * asked for. Instead the owner must have signed in to Clerk again within
     * the last few minutes — the cashier uses the app but cannot pass Clerk's
     * check, since its code goes to the owner's email.
     *
     * Every unlock token issued for the old PIN stops working.
     */
    public function update(SetPinRequest $request): JsonResponse
    {
        if (! ClerkSession::fromRequest($request)->verifiedWithin(self::REVERIFICATION_MINUTES)) {
            return $this->reverificationRequired();
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $merchant->forceFill(['pin_hash' => Hash::make($request->string('pin')->value())])->save();

        return response()->json(['message' => 'PIN updated.']);
    }

    /**
     * Shaped like Clerk's own reverification error, so `useReverification()`
     * in the app recognises it, asks the owner to verify, and retries.
     */
    private function reverificationRequired(): JsonResponse
    {
        return response()->json([
            'message' => 'Sign in again to change the PIN.',
            'code' => 'reverification_required',
            'clerk_error' => [
                'type' => 'forbidden',
                'reason' => 'reverification-error',
                'metadata' => [
                    'reverification' => [
                        'level' => 'first_factor',
                        'afterMinutes' => self::REVERIFICATION_MINUTES,
                    ],
                ],
            ],
        ], 403);
    }
}
