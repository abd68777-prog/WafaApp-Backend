<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery Driver
    |--------------------------------------------------------------------------
    |
    | "lightotp" sends the code over WhatsApp through LightOTP. "log" writes it
    | to the log file instead, which is what local development and testing use
    | so no credits are spent and no real message is sent.
    |
    | Supported: "lightotp", "log"
    |
    */

    'driver' => env('OTP_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Code Rules
    |--------------------------------------------------------------------------
    |
    | LightOTP accepts at most 8 alphanumeric characters, so "length" must stay
    | within that. "resend_after" is deliberately above LightOTP's own 30 second
    | duplicate-send cooldown, which doubles on every repeat send.
    |
    */

    'length' => (int) env('OTP_LENGTH', 6),

    'ttl' => (int) env('OTP_TTL', 300),

    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    'resend_after' => (int) env('OTP_RESEND_AFTER', 60),

];
