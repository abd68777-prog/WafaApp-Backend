<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\ChangePinRequest;
use App\Http\Requests\Api\V1\Merchant\ResetPinRequest;
use App\Http\Requests\Api\V1\Merchant\UnlockPinRequest;
use App\Models\Merchant;
use App\Services\Clerk\ClerkSession;
use App\Services\Merchant\PinAttempts;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * The PIN that separates the owner from the cashier on a shared account
 * (contract §5.4).
 */
class PinController extends Controller
{
    /**
     * How recently the owner must have signed in to Clerk to reset a
     * forgotten PIN.
     */
    public const RESET_MAX_AGE_MINUTES = 5;

    public function __construct(
        private readonly PinUnlockToken $pinUnlockToken,
        private readonly PinAttempts $pinAttempts,
    ) {}

    /**
     * Check the PIN and hand back the token that opens the protected tabs.
     * The app keeps it in memory only, so closing the app locks them again.
     */
    public function unlock(UnlockPinRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $this->pinAttempts->check($merchant, $request->string('pin')->value());

        return $this->unlocked($merchant);
    }

    /**
     * Change the PIN with the current one. Every unlock token issued for the
     * old PIN, on every device, stops working; this device gets a new one.
     */
    public function update(ChangePinRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $this->pinAttempts->check($merchant, $request->string('current_pin')->value());

        return $this->setPin($merchant, $request->string('new_pin')->value());
    }

    /**
     * Set a new PIN without the old one, for an owner who forgot it: they sign
     * out of Clerk, sign in again, and have a few minutes to choose a new PIN.
     * The cashier signed in on the shop's phone cannot pass that check.
     */
    public function reset(ResetPinRequest $request): JsonResponse
    {
        if (! ClerkSession::fromRequest($request)->verifiedWithin(self::RESET_MAX_AGE_MINUTES)) {
            throw ApiException::of(ErrorCode::PinResetRequiresRecentLogin, 'Sign in again to reset the PIN.', [
                'max_age_seconds' => self::RESET_MAX_AGE_MINUTES * 60,
            ]);
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        return $this->setPin($merchant, $request->string('new_pin')->value());
    }

    private function setPin(Merchant $merchant, string $pin): JsonResponse
    {
        $merchant->forceFill(['pin_hash' => Hash::make($pin)])->save();

        return $this->unlocked($merchant);
    }

    private function unlocked(Merchant $merchant): JsonResponse
    {
        return response()->json(['data' => $this->pinUnlockToken->describe($merchant)]);
    }
}
