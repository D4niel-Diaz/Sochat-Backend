<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.guest' => \App\Http\Middleware\AuthGuest::class,
            'auth.admin' => \App\Http\Middleware\AuthAdmin::class,
            'rate.limit.ip' => \App\Http\Middleware\RateLimitByIP::class,
            'content.filter' => \App\Http\Middleware\ContentFilter::class,
            'block.abusive.ip' => \App\Http\Middleware\BlockAbusiveIP::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'request.size.limit' => \App\Http\Middleware\RequestSizeLimit::class,
        ]);

        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\RateLimitByIP::class,
            \App\Http\Middleware\ContentFilter::class,
            \App\Http\Middleware\BlockAbusiveIP::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\RequestSizeLimit::class . ':10', // 10MB limit
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
