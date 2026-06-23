<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiAuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_contains_auth_endpoints_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## Auth Endpoints', $contents);
    }

    public function test_openapi_contains_session_auth_paths_and_methods(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $sessionLoginPath = $this->resolvePath($spec, ['/api/v1/auth/session/login', '/v1/auth/session/login', '/auth/session/login']);
        $sessionMePath = $this->resolvePath($spec, ['/api/v1/auth/session/me', '/v1/auth/session/me', '/auth/session/me']);
        $sessionLogoutPath = $this->resolvePath($spec, ['/api/v1/auth/session/logout', '/v1/auth/session/logout', '/auth/session/logout']);

        $this->assertNotNull($sessionLoginPath);
        $this->assertNotNull($sessionMePath);
        $this->assertNotNull($sessionLogoutPath);

        $this->assertNotEmpty(data_get($spec, "paths.{$sessionLoginPath}.post"));
        $this->assertNotEmpty(data_get($spec, "paths.{$sessionMePath}.get"));
        $this->assertNotEmpty(data_get($spec, "paths.{$sessionLogoutPath}.post"));
    }

    public function test_security_behavior_for_auth_endpoints_in_spec(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $tokenLoginPath = $this->resolvePath($spec, ['/api/v1/auth/token', '/v1/auth/token', '/auth/token']);
        $sessionLoginPath = $this->resolvePath($spec, ['/api/v1/auth/session/login', '/v1/auth/session/login', '/auth/session/login']);
        $sessionMePath = $this->resolvePath($spec, ['/api/v1/auth/session/me', '/v1/auth/session/me', '/auth/session/me']);
        $sessionLogoutPath = $this->resolvePath($spec, ['/api/v1/auth/session/logout', '/v1/auth/session/logout', '/auth/session/logout']);

        $tokenLoginSecurity = data_get($spec, "paths.{$tokenLoginPath}.post.security", []);
        $sessionLoginSecurity = data_get($spec, "paths.{$sessionLoginPath}.post.security", []);
        $sessionMeSecurity = data_get($spec, "paths.{$sessionMePath}.get.security", []);
        $sessionLogoutSecurity = data_get($spec, "paths.{$sessionLogoutPath}.post.security", []);

        $this->assertTrue(empty($tokenLoginSecurity), 'Token login path should not require auth security.');
        $this->assertTrue(empty($sessionLoginSecurity), 'Session login path should not require auth security.');
        $this->assertFalse(empty($sessionMeSecurity), 'Session me path must be protected.');
        $this->assertFalse(empty($sessionLogoutSecurity), 'Session logout path must be protected.');

        $schemes = (array) data_get($spec, 'components.securitySchemes', []);
        $this->assertArrayHasKey('BearerAuth', $schemes);
    }

    public function test_login_validation_and_me_unauthenticated_contracts(): void
    {
        $this->postJson('/api/v1/auth/session/login', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated')
            ->assertJsonStructure(['errors']);
    }

    public function test_openapi_does_not_expose_password_or_token_examples(): void
    {
        $json = json_encode(
            $this->getJson('/docs/api.json')->assertOk()->json(),
            JSON_THROW_ON_ERROR
        );
        $this->assertIsString($json);

        $lower = strtolower($json);
        $this->assertStringNotContainsString('"password":"', $lower);
        $this->assertStringNotContainsString('"token":"', $lower);
    }

    private function resolvePath(array $spec, array $candidates): ?string
    {
        $paths = (array) data_get($spec, 'paths', []);
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $paths)) {
                return $candidate;
            }
        }

        return null;
    }
}

