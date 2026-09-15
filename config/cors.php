<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| The frontend lives in a separate project on its own origin, so every
| browser request to this API is cross-origin. Allowed origins are driven
| by the FRONTEND_URLS env var (comma separated) instead of a wildcard,
| because wildcards cannot be combined with credentialed requests.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URLS', env('FRONTEND_URL', 'http://localhost:3000')))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Let the frontend read pagination / rate-limit headers.
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 60 * 60 * 24,

    // Required only for Sanctum cookie/SPA mode. Harmless for Bearer tokens.
    'supports_credentials' => true,

];
