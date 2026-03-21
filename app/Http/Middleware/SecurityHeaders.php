<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets security headers on every API response.
 *
 * CSP FIX: Removed 'unsafe-inline' and 'unsafe-eval' from script-src.
 * These directives effectively disabled XSS protection in the browser.
 * For a JSON API backend, no inline scripts are ever served, so there is
 * no legitimate need for them. Frontend CSP is handled by Vercel headers.
 *
 * connect-src: Locked down to only the specific WS server origins via
 * ALLOWED_ORIGINS env var (falls back to permissive in development).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $wsOrigins = app()->environment('production')
            ? implode(' ', array_filter(explode(',', env('ALLOWED_ORIGINS', ''))))
            : 'ws: wss: http://localhost:* https://localhost:*';

        // Content Security Policy
        // NOTE: For an API-only backend, script-src 'none' would be ideal.
        // We use 'self' to allow any Laravel Telescope/Horizon admin UI.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; " .
            "script-src 'self'; " .                       // FIX: removed unsafe-inline + unsafe-eval
            "style-src 'self' 'unsafe-inline'; " .        // inline styles needed for admin UI only
            "img-src 'self' data:; " .
            "font-src 'self'; " .
            "connect-src 'self' {$wsOrigins}; " .
            "frame-ancestors 'none';"
        );

        // Strict Transport Security (production only — not valid on HTTP)
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        $response->headers->set('X-Frame-Options',        'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection',      '1; mode=block'); // legacy browsers
        $response->headers->set('Referrer-Policy',        'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        return $response;
    }
}
