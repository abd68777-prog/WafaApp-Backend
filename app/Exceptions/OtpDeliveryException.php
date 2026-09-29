<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The verification code could not be delivered.
 *
 * The exception message carries the provider detail for the logs; the client
 * only ever sees the error code and a generic message, so a billing or API-key
 * problem on our side never leaks to the customer.
 */
class OtpDeliveryException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        private readonly ErrorCode $errorCode = ErrorCode::ServerError,
        private readonly int $status = 503,
        private readonly string $publicMessage = 'Could not send the verification code right now. Please try again shortly.',
        private readonly array $details = [],
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
            ErrorCode::OtpResendTooSoon,
            429,
            'Please wait before requesting another code.',
            ['retry_after_seconds' => $retryAfter],
        );
    }

    public static function invalidPhone(): self
    {
        return new self(
            'The delivery provider rejected the destination phone number.',
            ErrorCode::ValidationFailed,
            422,
            'This phone number cannot receive the verification code.',
            ['fields' => ['phone' => ['unreachable']]],
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
        return $this->details['retry_after_seconds'] ?? null;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): JsonResponse
    {
        return ApiException::response($this->errorCode, $this->publicMessage, $this->details, $this->status);
    }
}
