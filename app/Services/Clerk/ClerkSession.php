<?php

namespace App\Services\Clerk;

use Illuminate\Http\Request;
use LogicException;

/**
 * The verified identity behind a Clerk session token.
 *
 * `email` is only present when the Clerk instance is configured to include it
 * as a custom claim. It feeds the free-trial fingerprint and links a new
 * dashboard account on its first sign-in.
 *
 * `firstFactorAgeMinutes` comes from Clerk's `fva` claim: minutes since the
 * user last proved who they are (password, email code, Google), or null when
 * the token does not say.
 */
final readonly class ClerkSession
{
    public function __construct(
        public string $userId,
        public ?string $sessionId,
        public ?string $authorizedParty,
        public ?string $email = null,
        public ?int $firstFactorAgeMinutes = null,
    ) {}

    /**
     * Whether the user signed in again, or re-verified, within the last
     * `$minutes` minutes.
     */
    public function verifiedWithin(int $minutes): bool
    {
        return $this->firstFactorAgeMinutes !== null
            && $this->firstFactorAgeMinutes >= 0
            && $this->firstFactorAgeMinutes <= $minutes;
    }

    /**
     * The session the `clerk` middleware attached to the request.
     */
    public static function fromRequest(Request $request): self
    {
        $session = $request->attributes->get(self::class);

        if (! $session instanceof self) {
            throw new LogicException('No Clerk session on this request; is the route behind the "clerk" middleware?');
        }

        return $session;
    }
}
