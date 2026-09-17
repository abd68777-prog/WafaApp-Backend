<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The verification code could not be delivered.
 *
 * The exception message carries the provider detail for the logs; the client
 * only ever sees `$publicMessage`, so a billing or API-key problem on our side
 * never leaks to the customer.
 */
class OtpDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 503,
        private readonly string $publicMessage = 'Could not send the verification code right now. Please try again shortly.',
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /**
     * The provider (or our own policy) is asking the client to wait.
     */
    public static function cooldown(int $retryAfter): self
    {
        return new self(
            "Verification code requested again after {$retryAfter}s of cooldown remained.",
            429,
            'Please wait before requesting another code.',
            $retryAfter,
        );
    }

    public static function invalidPhone(): self
    {
        return new self(
            'The delivery provider rejected the destination phone number.',
            422,
            'This phone number cannot receive the verification code.',
        );
    }

    /**
     * Anything on our side or the provider's: no credit, bad key, outage.
     */
    public static function unavailable(string $reason): self
    {
        return new self("The OTP provider could not deliver the message: {$reason}.");
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $payload = ['message' => $this->publicMessage];

        if ($this->retryAfter !== null) {
            $payload['retry_after'] = $this->retryAfter;
        }

        $response = response()->json($payload, $this->status);

        if ($this->retryAfter !== null) {
            $response->header('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }
}
