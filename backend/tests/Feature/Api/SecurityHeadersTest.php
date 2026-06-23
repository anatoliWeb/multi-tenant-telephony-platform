<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_response_contains_security_headers(): void
    {
        config()->set('security.headers.enabled', true);

        $response = $this->getJson('/api/v1/health')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_docs_routes_return_security_headers_and_remain_accessible_in_local_bypass_mode(): void
    {
        config()->set('security.headers.enabled', true);
        config()->set('api-docs.local_bypass', true);
        config()->set('app.env', 'local');

        $this->get('/docs/api')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/docs/api.json')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->get('/docs/api/portal')->assertOk()->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_absent_by_default_and_present_when_enabled_for_secure_requests(): void
    {
        config()->set('security.headers.enabled', true);
        config()->set('security.headers.hsts.enabled', false);

        $withoutHsts = $this->get('/api/v1/health')->assertOk();
        $this->assertFalse($withoutHsts->headers->has('Strict-Transport-Security'));

        config()->set('security.headers.hsts.enabled', true);
        config()->set('security.headers.hsts.max_age', 31536000);
        config()->set('security.headers.hsts.include_subdomains', true);
        config()->set('security.headers.hsts.preload', false);

        $withHsts = $this->withHeader('X-Forwarded-Proto', 'https')->get('/api/v1/health')->assertOk();
        $withHsts->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csp_header_modes_and_disable_switch(): void
    {
        config()->set('security.headers.enabled', true);
        config()->set('security.headers.content_security_policy.enabled', true);
        config()->set('security.headers.content_security_policy.report_only', false);

        $enforced = $this->get('/api/v1/health')->assertOk();
        $this->assertTrue($enforced->headers->has('Content-Security-Policy'));
        $this->assertFalse($enforced->headers->has('Content-Security-Policy-Report-Only'));

        config()->set('security.headers.content_security_policy.report_only', true);
        $reportOnly = $this->get('/api/v1/health')->assertOk();
        $this->assertFalse($reportOnly->headers->has('Content-Security-Policy'));
        $this->assertTrue($reportOnly->headers->has('Content-Security-Policy-Report-Only'));

        config()->set('security.headers.enabled', false);
        $disabled = $this->get('/api/v1/health')->assertOk();
        $this->assertFalse($disabled->headers->has('X-Content-Type-Options'));
    }
}
