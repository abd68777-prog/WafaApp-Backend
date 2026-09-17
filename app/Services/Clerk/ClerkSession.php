<?php

namespace App\Services\Clerk;

use Illuminate\Http\Request;
use LogicException;

/**
 * The verified identity behind a Clerk session token.
 */
final readonly class ClerkSession
{
    public function __construct(
        public string $userId,
        public ?string $sessionId,
        public ?string $authorizedParty,
    ) {}

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
