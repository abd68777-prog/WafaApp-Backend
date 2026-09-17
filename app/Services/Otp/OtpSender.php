<?php

namespace App\Services\Otp;

use App\Exceptions\OtpDeliveryException;

/**
 * Delivers a verification code to a phone number.
 *
 * The code is always generated and verified by this application; a sender only
 * carries the message.
 */
interface OtpSender
{
    /**
     * @param  string  $phoneE164  Destination in E.164 format.
     * @param  string  $idempotencyKey  Lets the provider drop a retried send.
     *
     * @throws OtpDeliveryException
     */
    public function send(string $phoneE164, string $code, string $idempotencyKey): void;
}
