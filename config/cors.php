<?php

/**
 * CORS Configuration
 *
 * Allows the Next.js frontend (localhost:3000) to make API requests
 * to the Laravel backend. Required for Sanctum SPA authentication.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Credentials must be true for Sanctum cookie-based auth
    'supports_credentials' => true,

];
