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

        $cooldown = $this->cooldownSeconds($response, $errorCode);

        if ($cooldown !== null) {
            return OtpDeliveryException::cooldown($cooldown);
        }

        if (in_array($errorCode, self::PHONE_ERRORS, true)) {
            return OtpDeliveryException::invalidPhone();
        }

        return OtpDeliveryException::unavailable($errorCode !== '' ? $errorCode : 'HTTP '.$response->status());
    }

    /**
     * LightOTP refuses a repeat send to the same number with a sentence, not a
     * code: "… Please wait 00:02:00 and try again." The wait doubles on every
     * repeat within six hours, so it can outlast our own fixed resend delay.
     */
    private function cooldownSeconds(Response $response, string $errorMessage): ?int
    {
        if (preg_match('/wait\s+(\d+):(\d{2}):(\d{2})/i', $errorMessage, $wait) === 1) {
            return max(1, (int) $wait[1] * 3600 + (int) $wait[2] * 60 + (int) $wait[3]);
        }

        // LightOTP's own per-IP request limit.
        if ($response->status() === 429) {
            return (int) ($response->header('Retry-After') ?: 60);
        }

        return null;
    }
}
