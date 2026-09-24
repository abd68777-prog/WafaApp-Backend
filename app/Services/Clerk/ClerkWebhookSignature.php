<?php

namespace App\Services\Clerk;

use Illuminate\Http\Request;

/**
 * Verifies that a webhook really comes from Clerk. Clerk delivers webhooks
 * through Svix, which signs `{svix-id}.{svix-timestamp}.{raw body}` with
 * HMAC-SHA256 under the endpoint's signing secret (`whsec_` + base64 key).
 *
 * Checked by hand rather than through the Svix SDK, so no dependency is
 * added for three headers and one HMAC.
 */
final class ClerkWebhookSignature
{
    /**
     * How far the delivery timestamp may drift from our clock, in seconds.
     * Older deliveries are refused so a captured request cannot be replayed.
     */
    private const TOLERANCE = 300;

    public function __construct(private readonly ?string $secret) {}

    public function isValid(Request $request): bool
    {
        $key = $this->signingKey();
        $id = (string) $request->header('svix-id');
        $timestamp = (string) $request->header('svix-timestamp');
        $signatures = (string) $request->header('svix-signature');

        if ($key === null || $id === '' || ! ctype_digit($timestamp) || $signatures === '') {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$request->getContent()}", $key, true));

        // The header may carry several signatures during a secret rotation.
        foreach (explode(' ', $signatures) as $signature) {
            [$version, $value] = array_pad(explode(',', $signature, 2), 2, '');

            if ($version === 'v1' && hash_equals($expected, $value)) {
                return true;
            }
        }

        return false;
    }

    private function signingKey(): ?string
    {
        if (blank($this->secret) || ! str_starts_with($this->secret, 'whsec_')) {
            return null;
        }

        $key = base64_decode(substr($this->secret, strlen('whsec_')), true);

        return $key === false || $key === '' ? null : $key;
    }
}
