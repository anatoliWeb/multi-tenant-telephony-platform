<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiSwaggerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_spec_exposes_swagger_auth_security_schemes_and_route_security(): void
    {
        $this->get('/docs/api')->assertOk();

        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $this->assertIsArray($spec);
        $this->assertStringStartsWith('3.', (string) data_get($spec, 'openapi'));

        $securitySchemes = (array) data_get($spec, 'components.securitySchemes', []);
        $this->assertArrayHasKey('BearerAuth', $securitySchemes);
        $this->assertArrayHasKey('ExternalChatToken', $securitySchemes);
        $this->assertArrayHasKey('SanctumSession', $securitySchemes);
        $this->assertArrayHasKey('WebhookSignature', $securitySchemes);
        $this->assertArrayHasKey('WebhookTimestamp', $securitySchemes);

        $paths = (array) data_get($spec, 'paths', []);
        $this->assertNotEmpty($paths);
        $pathKeys = array_keys($paths);
        $this->assertFalse(collect($pathKeys)->contains('/broadcasting/auth'));

        $chatMessagesPath = $this->findPath(
            $paths,
            '/chat/conversations/{conversation}/messages'
        );
        $this->assertNotNull($chatMessagesPath, 'Expected protected chat messages route in OpenAPI spec.');
        $chatMessagesPostSecurity = data_get($paths[$chatMessagesPath], 'post.security', []);
        $this->assertIsArray($chatMessagesPostSecurity);
        $this->assertTrue(
            collect($chatMessagesPostSecurity)->contains(fn ($sec) => isset($sec['BearerAuth'])),
            'Expected BearerAuth requirement on protected chat route.'
        );

        $externalMessagesPath = $this->findPath(
            $paths,
            '/chat/external/messages'
        );
        $this->assertNotNull($externalMessagesPath, 'Expected external message route in OpenAPI spec.');
        $externalPostSecurity = data_get($paths[$externalMessagesPath], 'post.security', []);
        $this->assertIsArray($externalPostSecurity);
        $this->assertTrue(
            collect($externalPostSecurity)->contains(fn ($sec) => isset($sec['ExternalChatToken'])),
            'Expected ExternalChatToken requirement on external message route.'
        );

        $incomingWebhookPath = $this->findPath(
            $paths,
            '/chat/external/webhooks/{endpoint}'
        );
        $this->assertNotNull($incomingWebhookPath, 'Expected incoming webhook route in OpenAPI spec.');
        $incomingWebhookPostSecurity = data_get($paths[$incomingWebhookPath], 'post.security', []);
        $this->assertIsArray($incomingWebhookPostSecurity);
        $this->assertTrue(
            collect($incomingWebhookPostSecurity)->contains(
                fn ($sec) => isset($sec['WebhookSignature'], $sec['WebhookTimestamp'])
            ),
            'Expected webhook signature + timestamp requirements on incoming webhook route.'
        );

        $specJson = json_encode($spec, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('token_hash', $specJson);
        $this->assertStringNotContainsString('webhook_secret', $specJson);
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function findPath(array $paths, string $needleSuffix): ?string
    {
        foreach (array_keys($paths) as $path) {
            if (str_ends_with((string) $path, $needleSuffix)) {
                return (string) $path;
            }
        }

        return null;
    }
}

