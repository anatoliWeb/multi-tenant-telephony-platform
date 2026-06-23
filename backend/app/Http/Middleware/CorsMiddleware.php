<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CorsMiddleware
 *
 * Centralized CORS handler for API and selected routes.
 *
 * Responsibilities:
 * - Adds CORS headers based on config/cors.php
 * - Handles preflight (OPTIONS) requests
 * - Applies only to configured paths
 *
 * This allows removing CORS logic from nginx and keeping it inside Laravel.
 */
class CorsMiddleware
{
    /**
     * Handle incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if request path is not configured for CORS
        if (!$this->shouldHandle($request)) {
            return $next($request);
        }

        // Build headers based on config
        $headers = $this->buildHeaders($request);

        /**
         * Handle preflight request (OPTIONS)
         *
         * Browser sends OPTIONS before actual request
         * to check if it's allowed.
         */
        if ($request->isMethod('OPTIONS')) {
            return response()
                ->noContent(204)
                ->withHeaders($headers);
        }

        // Process normal request
        $response = $next($request);

        // Attach CORS headers to response
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * Determine if request should be handled by CORS middleware.
     *
     * Matches request path against config/cors.php "paths".
     *
     * @param Request $request
     * @return bool
     */
    private function shouldHandle(Request $request): bool
    {
        $paths = config('cors.paths', []);

        foreach ($paths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build CORS headers based on configuration.
     *
     * @param Request $request
     * @return array<string, string>
     */
    private function buildHeaders(Request $request): array
    {
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedMethods = config('cors.allowed_methods', ['*']);
        $allowedHeaders = config('cors.allowed_headers', ['*']);
        $supportsCredentials = (bool) config('cors.supports_credentials', false);

        $origin = (string) $request->headers->get('Origin', '');

        $allowOrigin = $this->resolveAllowedOrigin(
            $origin,
            $allowedOrigins,
            $supportsCredentials
        );

        return [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => implode(', ', $allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $allowedHeaders),
            'Access-Control-Allow-Credentials' => $supportsCredentials ? 'true' : 'false',
            'Vary' => 'Origin',
        ];
    }

    /**
     * Resolve allowed origin dynamically.
     *
     * Handles:
     * - wildcard "*"
     * - specific domains
     * - credentials compatibility
     *
     * @param string $origin
     * @param array $allowedOrigins
     * @param bool $supportsCredentials
     * @return string
     */
    private function resolveAllowedOrigin(
        string $origin,
        array $allowedOrigins,
        bool $supportsCredentials
    ): string {
        // Allow all origins
        if (in_array('*', $allowedOrigins, true)) {
            return $supportsCredentials && $origin !== '' ? $origin : '*';
        }

        // Allow only specific origins
        return in_array($origin, $allowedOrigins, true) ? $origin : '';
    }
}
