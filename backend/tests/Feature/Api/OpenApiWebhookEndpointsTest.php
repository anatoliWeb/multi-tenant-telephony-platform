<?php

namespace Tests\Feature\Api;

use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class OpenApiWebhookEndpointsTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_docs_contains_webhook_endpoints_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## Webhook Endpoints', $contents);
    }

    public function test_openapi_contains_webhook_paths_with_expected_security_and_request_body(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $managementPath = $this->resolvePath($spec, [
            '/api/v1/chat/webhook-endpoints',
            '/v1/chat/webhook-endpoints',
            '/chat/webhook-endpoints',
        ]);
        $updatePath = $this->resolvePath($spec, [
            '/api/v1/chat/webhook-endpoints/{endpoint}',
            '/v1/chat/webhook-endpoints/{endpoint}',
            '/chat/webhook-endpoints/{endpoint}',
        ]);
        $incomingPath = $this->resolvePath($spec, [
            '/api/v1/chat/external/webhooks/{endpoint}',
            '/api/v1/chat/external/webhooks/{endpoint:uuid}',
            '/v1/chat/external/webhooks/{endpoint}',
            '/chat/external/webhooks/{endpoint}',
        ]);
        $deliveriesPath = $this->resolvePath($spec, [
            '/api/v1/chat/conversations/{conversation}/webhook-deliveries',
            '/v1/chat/conversations/{conversation}/webhook-deliveries',
            '/chat/conversations/{conversation}/webhook-deliveries',
        ]);

        $this->assertNotNull($managementPath);
        $this->assertNotNull($updatePath);
        $this->assertNotNull($incomingPath);
        $this->assertNotNull($deliveriesPath);

        $this->assertNotEmpty(data_get($spec, "paths.{$managementPath}.get"));
        $this->assertNotEmpty(data_get($spec, "paths.{$managementPath}.post"));
        $this->assertNotEmpty(data_get($spec, "paths.{$updatePath}.patch"));
        $this->assertNotEmpty(data_get($spec, "paths.{$incomingPath}.post"));
        $this->assertNotEmpty(data_get($spec, "paths.{$deliveriesPath}.get"));

        $this->assertNotEmpty(data_get($spec, "paths.{$managementPath}.post.requestBody"));
        $this->assertNotEmpty(data_get($spec, "paths.{$updatePath}.patch.requestBody"));

        $managementSecurity = (array) data_get($spec, "paths.{$managementPath}.get.security", []);
        $incomingSecurity = (array) data_get($spec, "paths.{$incomingPath}.post.security", []);
        $this->assertNotEmpty($managementSecurity);
        $this->assertNotEmpty($incomingSecurity);

        $incomingSecurityJson = json_encode($incomingSecurity, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('WebhookSignature', $incomingSecurityJson);
        $this->assertStringContainsString('WebhookTimestamp', $incomingSecurityJson);
    }

    public function test_spec_does_not_expose_webhook_secrets_or_token_hash(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $serialized = json_encode($spec, JSON_THROW_ON_ERROR);
        $this->assertIsString($serialized);
        $lower = strtolower($serialized);

        $this->assertStringNotContainsString('webhook_secret', $lower);
        $this->assertStringNotContainsString('token_hash', $lower);
    }

    public function test_webhook_management_runtime_auth_and_permission_contract(): void
    {
        $this->getJson('/api/v1/chat/webhook-endpoints')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/chat/webhook-endpoints')->assertStatus(403);

        $user = User::factory()->create();
        $this->prepareTenantChatUser($user, ['chat.webhooks.view']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/chat/webhook-endpoints')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_incoming_webhook_invalid_signature_returns_forbidden(): void
    {
        $creator = User::factory()->create();
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'OpenAPI Incoming Webhook Conversation',
            'owner_id' => $creator->id,
            'created_by' => $creator->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);
        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Incoming Test',
            'url' => 'https://example.test/webhook',
            'secret' => 'secret-123',
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $creator->id,
            'metadata' => [],
        ]);

        $payload = [
            'event' => 'message.created',
            'conversation_id' => $conversation->id,
            'external_provider' => 'openapi-test-provider',
            'external_message_id' => 'wh-invalid-signature-1',
            'body' => 'Hello',
            'type' => 'text',
        ];

        $this->postJson('/api/v1/chat/external/webhooks/'.$endpoint->uuid, $payload)
            ->assertStatus(403);
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
