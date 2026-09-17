<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Writes the code to the log instead of sending it.
 *
 * This is the local and testing driver: it spends no LightOTP credit and sends
 * no real message. It deliberately logs the code in clear text, which is why it
 * must never be the driver in production.
 */
final class LogOtpSender implements OtpSender
{
    public function send(string $phoneE164, string $code, string $idempotencyKey): void
    {
        Log::info('OTP code issued (log driver).', [
            'phone' => $phoneE164,
            'code' => $code,
        ]);
    }
}
