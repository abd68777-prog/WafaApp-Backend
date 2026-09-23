<?php

namespace App\Services\Clerk;

use App\Exceptions\InvalidClerkTokenException;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Verifies Clerk session tokens locally with the instance's PEM public key,
 * so no request to Clerk is made per API call.
 */
final class ClerkTokenVerifier
{
    /**
     * @param  list<string>  $authorizedParties  Web origins allowed in the `azp` claim.
     */
    public function __construct(
        private readonly ?string $publicKey,
        private readonly array $authorizedParties,
        private readonly int $clockSkew,
    ) {}

    /**
     * @throws InvalidClerkTokenException
     */
    public function verify(string $token): ClerkSession
    {
        if (blank($this->publicKey)) {
            throw new RuntimeException('Clerk is not configured: set CLERK_JWT_KEY.');
        }

        $claims = $this->decode($token);

        // The library only checks `exp` when present, and every Clerk session
        // token has one, so a token without it is not a Clerk token.
        if (! isset($claims->exp) || ! is_string($claims->sub ?? null) || $claims->sub === '') {
            throw new InvalidClerkTokenException('Token is missing required claims.');
        }

        // `azp` is the browser Origin that requested the token. Native apps
        // (the Expo merchant app) have no Origin, so Clerk omits the claim:
        // an absent `azp` is accepted, a present one must be on the allowlist.
        $authorizedParty = $claims->azp ?? null;

        if ($authorizedParty !== null && ! in_array($authorizedParty, $this->authorizedParties, true)) {
            throw new InvalidClerkTokenException("Token issued for an unlisted origin [{$authorizedParty}].");
        }

        if (($claims->sts ?? null) === 'pending') {
            throw new InvalidClerkTokenException('Session is still pending.');
        }

        return new ClerkSession(
            $claims->sub,
            $claims->sid ?? null,
            $authorizedParty,
            is_string($claims->email ?? null) ? $claims->email : null,
            $this->firstFactorAge($claims),
        );
    }

    /**
     * `fva` is `[first factor age, second factor age]` in minutes, with -1
     * meaning never verified.
     */
    private function firstFactorAge(object $claims): ?int
    {
        $factorAges = $claims->fva ?? null;

        if (! is_array($factorAges) || ! is_int($factorAges[0] ?? null)) {
            return null;
        }

        return $factorAges[0];
    }

    /**
     * Signature, algorithm, `exp` and `nbf` are all enforced by the library.
     * Only RS256 is accepted, so a token signed with a shared secret fails.
     */
    private function decode(string $token): object
    {
        // `leeway` is a global static on the library; restore it so this
        // verifier never changes JWT handling elsewhere in the process.
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->clockSkew;

        try {
            return JWT::decode($token, new Key($this->publicKey, 'RS256'));
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $exception) {
            throw new InvalidClerkTokenException($exception->getMessage(), $exception);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }
}
