<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A refusal the apps are expected to handle, in the contract's shape:
 *
 *     { "error": { "code": "STAMP_INTERVAL", "message": "…", "details": { … } } }
 *
 * The HTTP status comes from the code. When `details` carries
 * `retry_after_seconds`, it is also sent as the Retry-After header.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function of(ErrorCode $errorCode, string $message, array $details = []): self
    {
        return new self($errorCode, $message, $details);
    }

    /**
     * The `error` object alone, for places that embed it in a successful
     * response (the scan preview's `blocked_reason`).
     *
     * @return array{code: string, message: string, details?: array<string, mixed>}
     */
    public function toErrorBody(): array
    {
        return self::body($this->errorCode, $this->getMessage(), $this->details);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): JsonResponse
    {
        return self::response($this->errorCode, $this->getMessage(), $this->details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function response(ErrorCode $errorCode, string $message, array $details = [], ?int $status = null): JsonResponse
    {
        $response = response()->json(['error' => self::body($errorCode, $message, $details)], $status ?? $errorCode->status());

        if (isset($details['retry_after_seconds'])) {
            $response->header('Retry-After', (string) $details['retry_after_seconds']);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{code: string, message: string, details?: array<string, mixed>}
     */
    private static function body(ErrorCode $errorCode, string $message, array $details): array
    {
        $body = ['code' => $errorCode->value, 'message' => $message];

        if ($details !== []) {
            $body['details'] = $details;
        }

        return $body;
    }
}
