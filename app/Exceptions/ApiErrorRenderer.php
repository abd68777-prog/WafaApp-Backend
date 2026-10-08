<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every exception on an API route into the contract's error shape, so
 * the apps parse one format with one zod schema.
 *
 * Exceptions that render themselves (ApiException, OtpDeliveryException,
 * InvalidClerkTokenException) never reach here. By the time this runs,
 * Laravel has already turned a missing model into a 404 and a failed gate or
 * token ability into a 403.
 */
final class ApiErrorRenderer
{
    /**
     * Validation rules reported under a short, stable name instead of
     * Laravel's class-like rule name. Anything not listed is reported in
     * snake case (`exists`, `in`, `prohibited`…).
     */
    private const RULE_NAMES = [
        'Required' => 'required',
        'RequiredIf' => 'required',
        'RequiredUnless' => 'required',
        'RequiredWith' => 'required',
        'RequiredWithout' => 'required',
        'RequiredWithoutAll' => 'required',
        'RequiredArrayKeys' => 'required',
        'Present' => 'required',
        'Filled' => 'required',
        'Max' => 'max',
        'Min' => 'min',
        'Size' => 'size',
        'Unique' => 'taken',
        'Regex' => 'format',
        'Date' => 'format',
        'DateFormat' => 'format',
        'Email' => 'format',
        'Digits' => 'format',
        'DigitsBetween' => 'format',
        'Integer' => 'format',
        'Numeric' => 'format',
        'String' => 'format',
        'Boolean' => 'format',
        'Array' => 'format',
        'Uuid' => 'format',
        'Image' => 'format',
        'Mimes' => 'format',
        'Mimetypes' => 'format',
        'Enum' => 'format',
        'In' => 'format',
        'SyrianPhone' => 'format',
    ];

    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        return match (true) {
            // Already a finished response (`abort(response(...))`).
            $exception instanceof HttpResponseException => null,
            $exception instanceof ValidationException => $this->validationFailed($exception),
            $exception instanceof AuthenticationException => ApiException::response(ErrorCode::Unauthenticated, 'Unauthenticated.'),
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception),
            default => $this->serverError($exception),
        };
    }

    private function validationFailed(ValidationException $exception): JsonResponse
    {
        $failed = $exception->validator->failed();
        $fields = [];

        foreach ($exception->errors() as $field => $messages) {
            $rules = array_map($this->ruleName(...), array_keys($failed[$field] ?? []));

            // Errors added by hand (ValidationException::withMessages) carry
            // no failed rule.
            $fields[$field] = $rules !== [] ? array_values(array_unique($rules)) : ['invalid'];
        }

        return ApiException::response(ErrorCode::ValidationFailed, $exception->getMessage(), ['fields' => $fields]);
    }

    private function ruleName(string $rule): string
    {
        $shortName = class_basename($rule);

        return self::RULE_NAMES[$shortName] ?? Str::snake($shortName);
    }

    private function fromHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        return match ($status) {
            401 => ApiException::response(ErrorCode::Unauthenticated, 'Unauthenticated.'),
            403 => ApiException::response(ErrorCode::Forbidden, $exception->getPrevious() instanceof AuthorizationException
                ? 'Your role does not allow this action.'
                : 'This account may not perform this action.'),
            404 => ApiException::response(ErrorCode::NotFound, 'Resource not found.'),
            405 => ApiException::response(ErrorCode::NotFound, 'Method not allowed on this endpoint.', status: 405),
            429 => ApiException::response(ErrorCode::RateLimited, 'Too many requests.', [
                'retry_after_seconds' => (int) ($exception->getHeaders()['Retry-After'] ?? 60),
            ]),
            default => $status >= 500
                ? $this->serverError($exception)
                : ApiException::response(ErrorCode::Forbidden, $exception->getMessage() ?: 'Request refused.', status: $status),
        };
    }

    /**
     * Nothing about the failure leaves the server unless debugging is on.
     */
    private function serverError(Throwable $exception): JsonResponse
    {
        $details = config('app.debug') ? [
            'exception' => $exception::class,
            'reason' => $exception->getMessage(),
            'file' => $exception->getFile().':'.$exception->getLine(),
        ] : [];

        return ApiException::response(ErrorCode::ServerError, 'Unexpected server error.', $details);
    }
}
