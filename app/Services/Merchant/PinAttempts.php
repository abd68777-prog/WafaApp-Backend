<?php

namespace App\Services\Merchant;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Merchant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checks a PIN typed in the merchant app. A PIN is four to six digits, so it
 * is guessable: after five wrong tries in a row the shop is locked out of the
 * protected tabs for a while (contract PIN_LOCKED). A correct PIN resets the
 * count.
 */
final class PinAttempts
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    /**
     * @throws ApiException PIN_LOCKED while locked out, PIN_INVALID when wrong.
     */
    public function check(Merchant $merchant, string $pin): void
    {
        $key = $this->key($merchant);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ApiException::of(ErrorCode::PinLocked, 'Too many wrong PINs; try again later.', [
                'retry_after_seconds' => max(1, RateLimiter::availableIn($key)),
            ]);
        }

        if ($merchant->pin_hash === null || ! Hash::check($pin, $merchant->pin_hash)) {
            $attempts = RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            throw ApiException::of(ErrorCode::PinInvalid, 'This PIN is not correct.', [
                'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $attempts),
            ]);
        }

        RateLimiter::clear($key);
    }

    private function key(Merchant $merchant): string
    {
        return 'pin-attempts:'.$merchant->id;
    }
}
