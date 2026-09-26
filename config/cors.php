<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | The React storefront runs on its own origin, so the browser will refuse
    | the JSON responses unless the origin is allow-listed here. This is
    | deliberately env-driven instead of a wildcard: a token-authenticated API
    | reachable from any origin is a credential-theft waiting to happen, and
    | the production storefront is a known, fixed host.
    |
    | Set API_CORS_ORIGINS in .env, comma separated:
    |   https://astroemerging.com,https://www.astroemerging.com,http://localhost:5173
    |
    | If the value is left empty the list falls back to the production domain
    | plus the usual Vite/CRA dev-server ports, so a fresh checkout works.
    |
    */

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('API_CORS_ORIGINS', ''))
    )) ?: [
        'https://astroemerging.com',
        'https://www.astroemerging.com',
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
