<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiSchemaDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_openapi_contains_expected_core_chat_and_external_schemas(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $schemas = (array) data_get($spec, 'components.schemas', []);

        foreach ([
            'ApiSuccessResponse',
            'ApiErrorResponse',
            'ValidationErrorResponse',
            'PaginatedResponse',
            'PaginationMeta',
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
            'ExternalMessageRequest',
            'IncomingWebhookRequest',
            'MetaBootstrapResponse',
            'MetaRbacResponse',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas, "Expected {$schemaName} schema in OpenAPI components.");
        }
    }

    public function test_schema_definitions_include_safe_fields_and_exclude_forbidden_fields(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $schemas = (array) data_get($spec, 'components.schemas', []);

        $chatMessage = (array) data_get($schemas, 'ChatMessage.properties', []);
        $this->assertArrayHasKey('id', $chatMessage);
        $this->assertArrayHasKey('conversation_id', $chatMessage);
        $this->assertArrayHasKey('sender_id', $chatMessage);
        $this->assertArrayHasKey('status', $chatMessage);

        $webhookEndpoint = (array) data_get($schemas, 'ChatWebhookEndpoint.properties', []);
        $this->assertArrayHasKey('id', $webhookEndpoint);
        $this->assertArrayHasKey('uuid', $webhookEndpoint);
        $this->assertArrayHasKey('name', $webhookEndpoint);
        $this->assertArrayHasKey('status', $webhookEndpoint);

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
        foreach ([
            'token_hash',
            'webhook_secret',
            'device_key',
            'user_agent',
            'ip_address',
            'disk',
            'path',
            'checksum',
            'raw_payload',
            'raw_response',
            'storage_path',
            'blocked_reason',
        ] as $forbiddenField) {
            $this->assertStringNotContainsString($forbiddenField, $serialized);
        }
    }

    public function test_docs_contains_openapi_schema_definitions_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## OpenAPI Schema Definitions', $contents);
    }
}
