<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Apply baseline response security headers.
     *
     * Header policy remains config-driven so local debugging can stay flexible while
     * production defaults remain strict.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! (bool) config('security.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()'));

        $this->applyContentSecurityPolicy($response);
        $this->applyHsts($request, $response);

        return $response;
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        if (! (bool) config('security.headers.content_security_policy.enabled', true)) {
            return;
        }

        $policy = trim((string) config('security.headers.content_security_policy.value', ''));
        if ($policy === '') {
            return;
        }

        $header = (bool) config('security.headers.content_security_policy.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $policy);
    }

    private function applyHsts(Request $request, Response $response): void
    {
        // HSTS is added only for secure requests to avoid unsafe preload/redirect assumptions in local HTTP flows.
        if (! (bool) config('security.headers.hsts.enabled', false)) {
            return;
        }

        $forwardedProto = mb_strtolower((string) $request->headers->get('X-Forwarded-Proto', ''));
        $isSecure = $request->isSecure() || $forwardedProto === 'https';
        if (! $isSecure) {
            return;
        }

        $maxAge = max(0, (int) config('security.headers.hsts.max_age', 31536000));
        $parts = ["max-age={$maxAge}"];

        if ((bool) config('security.headers.hsts.include_subdomains', true)) {
            $parts[] = 'includeSubDomains';
        }

        if ((bool) config('security.headers.hsts.preload', false)) {
            $parts[] = 'preload';
        }

        $response->headers->set('Strict-Transport-Security', implode('; ', $parts));
    }
}
