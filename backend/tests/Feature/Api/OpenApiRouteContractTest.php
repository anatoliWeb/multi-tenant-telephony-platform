<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiRouteContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_openapi_root_structure_is_valid(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertIsArray($spec);
        $this->assertNotEmpty(data_get($spec, 'openapi'));
        $this->assertNotEmpty(data_get($spec, 'info'));
        $this->assertNotEmpty(data_get($spec, 'paths'));
        $this->assertNotEmpty(data_get($spec, 'components'));
    }

    public function test_critical_api_groups_and_security_schemes_exist(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $paths = (array) data_get($spec, 'paths', []);
        $securitySchemes = (array) data_get($spec, 'components.securitySchemes', []);
        $schemas = (array) data_get($spec, 'components.schemas', []);

        foreach ([
            '/auth/session/login',
            '/meta/bootstrap',
            '/users',
            '/roles',
            '/permissions',
            '/notifications',
            '/chat/conversations',
            '/chat/conversations/{conversation}/messages',
            '/chat/webhook-endpoints',
            '/chat/external/messages',
            '/chat/external/webhooks/{endpoint}',
        ] as $criticalPathSuffix) {
            $this->assertNotNull(
                $this->findPath($paths, $criticalPathSuffix),
                "Missing critical path ending with [{$criticalPathSuffix}]"
            );
        }

        foreach ([
            'BearerAuth',
            'SanctumSession',
            'ExternalChatToken',
            'WebhookSignature',
            'WebhookTimestamp',
        ] as $securityScheme) {
            $this->assertArrayHasKey($securityScheme, $securitySchemes, "Missing security scheme [{$securityScheme}]");
        }

        foreach ([
            'ApiSuccessResponse',
            'ApiErrorResponse',
            'ValidationErrorResponse',
            'PaginationMeta',
            'User',
            'ChatConversation',
            'ChatMessage',
            'ChatWebhookEndpoint',
            'ExternalMessageRequest',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas, "Missing schema [{$schemaName}]");
        }
    }

    public function test_endpoint_security_contract_is_consistent(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $paths = (array) data_get($spec, 'paths', []);

        $chatConversationsPath = $this->findPath($paths, '/chat/conversations');
        $usersPath = $this->findPath($paths, '/users');
        $webhookManagementPath = $this->findPath($paths, '/chat/webhook-endpoints');
        $sessionLoginPath = $this->findPath($paths, '/auth/session/login');
        $tokenLoginPath = $this->findPath($paths, '/auth/login');
        $externalMessagesPath = $this->findPath($paths, '/chat/external/messages');
        $incomingWebhookPath = $this->findPath($paths, '/chat/external/webhooks/{endpoint}');

        $this->assertNotNull($chatConversationsPath);
        $this->assertNotNull($usersPath);
        $this->assertNotNull($webhookManagementPath);
        $this->assertNotNull($sessionLoginPath);
        $this->assertNotNull($externalMessagesPath);
        $this->assertNotNull($incomingWebhookPath);

        $this->assertNotEmpty(data_get($paths[$chatConversationsPath], 'get.security', []));
        $this->assertNotEmpty(data_get($paths[$usersPath], 'get.security', []));
        $this->assertNotEmpty(data_get($paths[$webhookManagementPath], 'get.security', []));

        $this->assertEmpty(data_get($paths[$sessionLoginPath], 'post.security', []));
        if ($tokenLoginPath !== null) {
            $this->assertEmpty(data_get($paths[$tokenLoginPath], 'post.security', []));
        }

        $externalSecurity = (array) data_get($paths[$externalMessagesPath], 'post.security', []);
        $this->assertTrue(
            collect($externalSecurity)->contains(fn (array $sec): bool => array_key_exists('ExternalChatToken', $sec)),
            'External messages route must require ExternalChatToken security.'
        );

        $incomingSecurity = (array) data_get($paths[$incomingWebhookPath], 'post.security', []);
        $this->assertTrue(
            collect($incomingSecurity)->contains(fn (array $sec): bool => isset($sec['WebhookSignature'], $sec['WebhookTimestamp'])),
            'Incoming webhook route must require WebhookSignature + WebhookTimestamp.'
        );
    }

    public function test_spec_excludes_internal_routes_and_forbidden_strings_and_docs_has_major_sections(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $paths = array_keys((array) data_get($spec, 'paths', []));
        $schemas = (array) data_get($spec, 'components.schemas', []);
        $safetyScopedSchemas = [];
        foreach ([
            'User',
            'Role',
            'Permission',
            'ChatConversation',
            'ChatMessage',
            'ChatAttachment',
            'ChatParticipant',
            'ChatDeviceRead',
            'ChatReadState',
            'ChatWebhookEndpoint',
            'ChatWebhookDeliverySummary',
            'MetaBootstrapResponse',
            'MetaRbacResponse',
        ] as $safeSchemaName) {
            $safetyScopedSchemas[$safeSchemaName] = data_get($schemas, $safeSchemaName);
        }
        $serialized = strtolower((string) json_encode($safetyScopedSchemas, JSON_THROW_ON_ERROR));

        $this->assertFalse(collect($paths)->contains('/broadcasting/auth'));
        $this->assertFalse(collect($paths)->contains(fn (string $path): bool => str_starts_with($path, '/admin')));
        $this->assertFalse(collect($paths)->contains(fn (string $path): bool => str_contains($path, '/telescope')));
        $this->assertFalse(collect($paths)->contains(fn (string $path): bool => str_contains($path, '/horizon')));

        foreach ([
            'token_hash',
            'webhook_secret',
            'device_key',
            'user_agent',
            'ip_address',
            'disk',
            'checksum',
            'raw_payload',
            'raw_response',
        ] as $forbiddenString) {
            $this->assertStringNotContainsString($forbiddenString, $serialized);
        }

        $docs = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($docs);
        $this->assertStringContainsString('## Auth Endpoints', $docs);
        $this->assertStringContainsString('## Chat Endpoints', $docs);
        $this->assertStringContainsString('## Webhook Endpoints', $docs);
        $this->assertStringContainsString('## External API Endpoints', $docs);
        $this->assertStringContainsString('## OpenAPI Schema Definitions', $docs);
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
