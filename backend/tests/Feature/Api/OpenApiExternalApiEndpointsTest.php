<?php

namespace Tests\Feature\Api;

use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\Chat\ExternalChatTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenApiExternalApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_docs_contains_external_api_endpoints_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## External API Endpoints', $contents);
    }

    public function test_openapi_contains_external_api_paths_with_security_and_request_body(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $messagesPath = $this->resolvePath($spec, [
            '/api/v1/chat/external/messages',
            '/v1/chat/external/messages',
            '/chat/external/messages',
        ]);
        $incomingPath = $this->resolvePath($spec, [
            '/api/v1/chat/external/webhooks/{endpoint:uuid}',
            '/api/v1/chat/external/webhooks/{endpoint}',
            '/v1/chat/external/webhooks/{endpoint}',
            '/chat/external/webhooks/{endpoint}',
        ]);

        $this->assertNotNull($messagesPath);
        $this->assertNotNull($incomingPath);
        $this->assertNotEmpty(data_get($spec, "paths.{$messagesPath}.post"));
        $this->assertNotEmpty(data_get($spec, "paths.{$incomingPath}.post"));
        $this->assertNotEmpty(data_get($spec, "paths.{$messagesPath}.post.requestBody"));
        $this->assertNotEmpty(data_get($spec, "paths.{$incomingPath}.post.requestBody"));

        $externalSecurity = (array) data_get($spec, "paths.{$messagesPath}.post.security", []);
        $incomingSecurity = (array) data_get($spec, "paths.{$incomingPath}.post.security", []);
        $this->assertTrue(
            collect($externalSecurity)->contains(fn (array $sec): bool => array_key_exists('ExternalChatToken', $sec)),
            'Expected ExternalChatToken security requirement for external message route.'
        );
        $this->assertTrue(
            collect($incomingSecurity)->contains(fn (array $sec): bool => isset($sec['WebhookSignature'], $sec['WebhookTimestamp'])),
            'Expected WebhookSignature + WebhookTimestamp requirement for incoming webhook route.'
        );
    }

    public function test_spec_does_not_expose_external_sensitive_keys(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $serialized = strtolower((string) json_encode($spec, JSON_THROW_ON_ERROR));

        $this->assertStringNotContainsString('token_hash', $serialized);
        $this->assertStringNotContainsString('webhook_secret', $serialized);
        $this->assertStringNotContainsString('x-chat-signature:', $serialized);
        $this->assertStringNotContainsString('authorization: bearer', $serialized);
    }

    public function test_external_message_runtime_invalid_token_and_missing_scope_contract(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer invalid_external_token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => 1,
            'external_provider' => 'crm',
            'external_message_id' => 'ext-invalid-token',
            'body' => 'payload',
        ])->assertStatus(401);

        $owner = User::factory()->create();
        $conversation = $this->makeExternalConversation($owner);
        $this->addParticipant($conversation, $owner);

        /** @var ExternalChatTokenService $tokenService */
        $tokenService = app(ExternalChatTokenService::class);
        $plainToken = $tokenService->generatePlainToken();
        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Send Scope',
            'url' => 'https://example.test/no-send-scope',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $owner->id,
            'metadata' => [
                'token_hash' => $tokenService->hashToken($plainToken),
                'token_hash_algo' => (string) config('chat.external_api.token_hash_algo', 'sha256'),
                'token_scopes' => ['chat.external.webhooks.view'],
            ],
        ]);

        $this->assertNotNull($endpoint);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$plainToken,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => $conversation->id,
            'external_provider' => 'crm',
            'external_message_id' => 'ext-missing-scope',
            'body' => 'payload',
        ])->assertStatus(403);
    }

    public function test_incoming_webhook_invalid_signature_returns_forbidden(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->makeExternalConversation($owner);
        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Incoming External Endpoint',
            'url' => 'https://example.test/incoming',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $owner->id,
            'metadata' => [],
        ]);

        $this->postJson('/api/v1/chat/external/webhooks/'.$endpoint->uuid, [
            'event' => 'message.created',
            'conversation_id' => $conversation->id,
            'external_provider' => 'provider-a',
            'external_message_id' => 'ext-invalid-signature',
            'body' => 'hello',
            'type' => 'text',
        ])->assertStatus(403);
    }

    public function test_external_routes_have_expected_rate_limit_and_scope_middlewares(): void
    {
        $routes = app('router')->getRoutes();
        $externalMessageRoute = $routes->getByName('api.v1.chat.external.messages.store');
        $incomingWebhookRoute = $routes->getByName('api.v1.chat.external.webhooks.handle');

        $this->assertNotNull($externalMessageRoute);
        $this->assertNotNull($incomingWebhookRoute);

        $externalMiddlewares = $externalMessageRoute?->gatherMiddleware() ?? [];
        $incomingMiddlewares = $incomingWebhookRoute?->gatherMiddleware() ?? [];

        $this->assertContains('throttle:chat-external-api', $externalMiddlewares);
        $this->assertContains('external.chat.scope:chat.external.messages.send', $externalMiddlewares);
        $this->assertContains('throttle:chat-external-api', $incomingMiddlewares);
    }

    private function makeExternalConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'OpenAPI External API Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);
    }

    private function addParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        return ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'can_invite' => true,
            'can_remove' => true,
            'can_manage' => true,
            'can_moderate' => true,
            'joined_at' => now(),
        ]);
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

