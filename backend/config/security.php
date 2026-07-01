<?php

$runtimeAppEnv = strtolower((string) env('APP_ENV', 'production'));
$isLocalViteEnvironment = in_array($runtimeAppEnv, ['local', 'testing'], true);

// Local Vite serves ES modules from browser-reachable localhost origins and uses
// WebSocket HMR, so we only open those sources for local/test development.
$scriptSrc = "'self' 'unsafe-inline' 'unsafe-eval'";
$connectSrc = "'self' ws: wss:";

if ($isLocalViteEnvironment) {
    $scriptSrc .= ' http://localhost:* http://127.0.0.1:*';
    $connectSrc .= ' http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:* wss://localhost:* wss://127.0.0.1:*';
}

$contentSecurityPolicy = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src {$scriptSrc}; connect-src {$connectSrc};";

return [
    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', false),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
        ],
        'content_security_policy' => [
            'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
            'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
            'value' => env('SECURITY_CSP_VALUE', $contentSecurityPolicy),
        ],
    ],
    'rate_limits' => [
        'enabled' => (bool) env('SECURITY_RATE_LIMITS_ENABLED', true),
        'auth_login' => [
            'max_attempts' => (int) env('AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('AUTH_LOGIN_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
        'api_docs' => [
            'max_attempts' => (int) env('API_DOCS_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_seconds' => (int) env('API_DOCS_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
        'chat_typing' => [
            'max_attempts' => (int) env('CHAT_TYPING_RATE_LIMIT_MAX_ATTEMPTS', 120),
            'decay_seconds' => (int) env('CHAT_TYPING_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
        'chat_attachments' => [
            'max_attempts' => (int) env('CHAT_ATTACHMENT_RATE_LIMIT_MAX_ATTEMPTS', 20),
            'decay_seconds' => (int) env('CHAT_ATTACHMENT_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
    ],
];
