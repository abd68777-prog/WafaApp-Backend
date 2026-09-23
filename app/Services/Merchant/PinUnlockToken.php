<?php

namespace App\Services\Merchant;

use App\Models\Merchant;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * Proof that the PIN was entered, sent back by the merchant app as the
 * `X-Pin-Token` header on the protected tabs.
 *
 * The token is encrypted with the application key, so the app cannot forge or
 * alter it. It carries the merchant, an expiry, and a fingerprint of the PIN
 * hash: setting a new PIN changes the fingerprint and voids every token issued
 * for the old one. No server-side state is kept.
 */
final class PinUnlockToken
{
    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(Merchant $merchant): array
    {
        $expiresAt = CarbonImmutable::now()->addHours((int) Setting::read('pin_unlock_hours', 12));

        $token = Crypt::encryptString(json_encode([
            'merchant' => $merchant->id,
            'pin' => $this->pinFingerprint($merchant),
            'expires' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function isValidFor(Merchant $merchant, ?string $token): bool
    {
        if (blank($token) || $merchant->pin_hash === null) {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return false;
        }

        return is_array($payload)
            && ($payload['merchant'] ?? null) === $merchant->id
            && is_string($payload['pin'] ?? null)
            && hash_equals($this->pinFingerprint($merchant), $payload['pin'])
            && is_int($payload['expires'] ?? null)
            && $payload['expires'] > CarbonImmutable::now()->getTimestamp();
    }

    private function pinFingerprint(Merchant $merchant): string
    {
        return hash_hmac('sha256', (string) $merchant->pin_hash, (string) config('app.key'));
    }
}
