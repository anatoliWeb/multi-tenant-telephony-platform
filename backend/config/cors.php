<?php

/**
 * ------------------------------------------------------------
 * CORS Configuration
 * ------------------------------------------------------------
 *
 * Centralized Cross-Origin Resource Sharing (CORS) settings.
 *
 * This configuration controls:
 * - Which routes allow cross-origin requests
 * - Which origins (domains) are allowed
 * - Which HTTP methods and headers are permitted
 *
 * Used by CorsMiddleware to dynamically apply headers.
 */

return [

    /**
     * ------------------------------------------------------------
     * Paths
     * ------------------------------------------------------------
     *
     * Routes where CORS should be applied.
     *
     * Example:
     * - api/* → all API routes
     * - login → authentication endpoint
     * - sanctum/csrf-cookie → if using Sanctum
     */
    'paths' => [
        'api/*',
        'broadcasting/auth',
        'login',
        'logout',
        'sanctum/csrf-cookie',
    ],

    /**
     * ------------------------------------------------------------
     * Allowed Methods
     * ------------------------------------------------------------
     *
     * HTTP methods allowed for cross-origin requests.
     */
    'allowed_methods' => ['*'],

    /**
     * ------------------------------------------------------------
     * Allowed Origins
     * ------------------------------------------------------------
     *
     * List of allowed frontend domains.
     *
     * Loaded from .env:
     * CORS_ALLOWED_ORIGINS=http://localhost:5173,https://yourdomain.com
     *
     * Supports multiple origins via comma-separated string.
     */
    'allowed_origins' => array_filter(
        array_map(
            'trim',
            explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200'))
        )
    ),

    /**
     * ------------------------------------------------------------
     * Allowed Headers
     * ------------------------------------------------------------
     *
     * Headers allowed in incoming requests.
     */
//    'allowed_headers' => ['*'],
    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'X-Socket-Id',
        'DNT',
    ],

    /**
     * ------------------------------------------------------------
     * Credentials Support
     * ------------------------------------------------------------
     *
     * Whether cookies / authorization headers are allowed.
     *
     * IMPORTANT:
     * - If true → '*' cannot be used for origins
     */
    'supports_credentials' => true,

];
