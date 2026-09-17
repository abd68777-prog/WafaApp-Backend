<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * A missing, malformed, expired or untrusted Clerk session token.
 *
 * The reason stays in the exception message for debugging; the client gets
 * the same 401 body as every other unauthenticated API request.
 */
class InvalidClerkTokenException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
