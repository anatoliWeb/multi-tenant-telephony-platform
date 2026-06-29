<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsMiddlewareTest extends TestCase
{
    public function test_preflight_allows_tenant_scoped_api_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/contacts', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:4200',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,x-tenant-id,content-type',
        ]);

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
        $this->assertStringContainsString('X-Tenant-ID', (string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('Authorization', (string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('Content-Type', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_unauthenticated_api_errors_keep_cors_headers(): void
    {
        $response = $this->getJson('/api/v1/contacts', [
            'Origin' => 'http://localhost:4200',
            'X-Tenant-ID' => 'tenant-demo',
        ]);

        $response->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertHeader('Access-Control-Allow-Headers');
    }
}
