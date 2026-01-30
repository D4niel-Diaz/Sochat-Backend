<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    'allowed_origins' => [
        env('FRONTEND_URL'),
        env('FRONTEND_URL_PROD'),
        'https://sochat-frontend-63y4.vercel.app',
        'https://sochat-frontend.vercel.app',
        'https://sochat-livid.vercel.app',
        'https://sochat-git-main-daniels-projects-8c2bbb7b.vercel.app',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
    ],

    'allowed_origins_patterns' => [
        'https://sochat-*.vercel.app',
        'https://sochat-frontend-*.vercel.app',
        'https://sochat-git-*.vercel.app',
        'https://*.vercel.app',
        'http://localhost:*',
        'http://127.0.0.1:*',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'Access-Control-Request-Method',
        'Access-Control-Request-Headers',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,
];
