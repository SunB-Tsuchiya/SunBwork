<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This file was added to provide an explicit, conservative CORS policy
    | for the application. Origins may be set via the `CORS_ALLOWED_ORIGINS`
    | environment variable (comma-separated). For development, defaults are
    | provided for common Vite dev ports. In production, set a specific
    | origin (or origins) for your frontend domain(s).
    |
    */

    // Paths that should receive CORS headers
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
        // Allow Inertia/XHR calls from dev frontends to application routes
        'superadmin/*',
        'admin/*',
    ],

    // HTTP methods allowed for CORS requests
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Allowed origins (comma-separated in env). Defaults target local dev hosts.
    // Include 127.0.0.1 for Vite dev servers that use the numeric localhost
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:5174,http://127.0.0.1:5173')))),

    // Patterns for allowed origins (unused by default)
    'allowed_origins_patterns' => [],

    // Allowed headers for CORS requests. Keep essential headers only.
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'Authorization',
        'Accept',
        'Origin',
    ],

    // Headers exposed to the browser
    'exposed_headers' => [],

    // How long the results of a preflight request can be cached (in seconds)
    'max_age' => 0,

    // Whether the response to the request can be exposed when the credentials flag is true.
    'supports_credentials' => true,
];
