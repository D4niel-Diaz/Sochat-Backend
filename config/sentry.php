<?php

return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'environment' => env('APP_ENV', 'production'),

    'breadcrumbs' => [
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES', false),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS', false),
    ],

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'before_send' => function ($event) {
        // Filter out sensitive information
        if (isset($event->request)) {
            unset($event->request->cookies);
            unset($event->request->env);
        }

        return $event;
    },

];
