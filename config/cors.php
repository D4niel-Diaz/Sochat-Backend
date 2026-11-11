<?php

$isLocal = env('APP_ENV', 'production') === 'local';

return [

    'paths' => [
        'api/*',
    ],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    // Exact origins. Extra ones can be added with FRONTEND_URL / FRONTEND_URL_PROD.
    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('FRONTEND_URL_PROD'),
        'https://sochat-livid.vercel.app',
        'https://sochat-frontend.vercel.app',
        'https://sochat-frontend-63y4.vercel.app',
    ])),

    // Real regexes (fruitcake/php-cors passes these straight to preg_match).
    'allowed_origins_patterns' => array_values(array_filter([
        // This project's Vercel production and preview deployments only
        '#^https://sochat(-[a-z0-9-]+)?\.vercel\.app$#',
        $isLocal ? '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#' : null,
    ])),

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 86400,

    // Auth uses Authorization bearer tokens, not cookies
    'supports_credentials' => false,
];
