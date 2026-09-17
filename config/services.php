<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | LightOTP delivers the customer's login code over WhatsApp. We generate
    | and verify the code ourselves; LightOTP only carries the message.
    */
    'lightotp' => [
        'key' => env('LIGHTOTP_API_KEY'),
        'base_url' => env('LIGHTOTP_BASE_URL', 'https://api.lightotp.com'),
        'language' => env('LIGHTOTP_LANGUAGE', 'ar'),
        'timeout' => (int) env('LIGHTOTP_TIMEOUT', 15),
    ],

    /*
    | Clerk signs merchant-app and admin-dashboard session tokens. They are
    | verified locally with the instance's PEM public key (Clerk Dashboard →
    | API keys → "JWT public key"). A .env value is one line, so literal "\n"
    | sequences are turned back into line breaks.
    */
    'clerk' => [
        'jwt_key' => str_replace('\n', "\n", (string) env('CLERK_JWT_KEY', '')) ?: null,
        'authorized_parties' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CLERK_AUTHORIZED_PARTIES', '')),
        ))),
        'clock_skew' => (int) env('CLERK_CLOCK_SKEW', 5),
    ],

];
