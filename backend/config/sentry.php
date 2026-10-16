<?php

declare(strict_types=1);

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Release identifier for deployment tracking
    'release' => env('SENTRY_RELEASE', '1.0.0'),

    // Environment matching APP_ENV (production, staging)
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Error and performance tracing sample rate
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    // Breadcrumbs tracking
    'breadcrumbs' => [
        'logs'         => true,
        'sql_queries'  => true,
        'sql_bindings' => false, // Per docs/04: NEVER log SQL bindings (avoids secrets/PII leak)
        'queue_info'   => true,
        'command_info' => true,
    ],

    // Security: Do NOT attach raw PII or headers by default
    'send_default_pii' => false,

    // Sensitive context keys scrubbed before transmission
    'context_lines' => 5,
];
