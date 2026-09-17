<?php

namespace App\Services\Otp;

use App\Exceptions\OtpDeliveryException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends the code over WhatsApp through LightOTP.
 *
 * LightOTP has no verify endpoint: it only delivers the code we generated, so
 * `otp_codes` stays the source of truth for verification.
 */
final class LightOtpSender implements OtpSender
{
    /**
     * Phone-related rejections from the provider. Our own validation should
     * catch these first, so reaching one means the number is undeliverable.
     */
    private const PHONE_ERRORS = [
        'InvalidphoneNumber',
        'PhoneNumberCanNotBeEmpty',
        'DestinationPhoneNumberIsRequired',
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $language,
        private readonly int $timeout,
    ) {}

    public function send(string $phoneE164, string $code, string $idempotencyKey): void
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($this->baseUrl.'/SendMessage', [
                'otpCode' => $code,
                'toPhoneE164' => $phoneE164,
                'languageCode' => $this->language,
                'idempotencyKey' => $idempotencyKey,
            ]);

        if ($response->successful()) {
            return;
        }

        throw $this->translateFailure($response);
    }

    private function translateFailure(Response $response): OtpDeliveryException
    {
        $errorCode = (string) $response->json('errorMessage', '');

        // Never log the code itself, only why the provider refused it.
        Log::error('LightOTP rejected an OTP message.', [
            'status' => $response->status(),
            'error' => $errorCode,
        ]);

        if ($response->status() === 429 || $this->isCooldown($errorCode)) {
            return OtpDeliveryException::cooldown(
                (int) ($response->header('Retry-After') ?: 60)
            );
        }

        if (in_array($errorCode, self::PHONE_ERRORS, true)) {
            return OtpDeliveryException::invalidPhone();
        }

        return OtpDeliveryException::unavailable($errorCode !== '' ? $errorCode : 'HTTP '.$response->status());
    }

    private function isCooldown(string $errorCode): bool
    {
        $normalized = mb_strtolower($errorCode);

        return str_contains($normalized, 'cooldown') || str_contains($normalized, 'duplicate');
    }
}
