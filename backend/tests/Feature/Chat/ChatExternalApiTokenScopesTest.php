<?php

namespace Tests\Feature\Chat;

use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ExternalChatTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatExternalApiTokenScopesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->sync($permissionIds);

        return $user;
    }

    private function makeExternalConversation(User $owner): Conversation
    {
        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'External Scope Conversation',
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
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'joined_at' => now(),
        ]);
    }

    private function validPayload(Conversation $conversation, string $externalMessageId = 'ext-scope-1'): array
    {
        return [
            'conversation_id' => $conversation->id,
            'external_provider' => 'crm-hub',
            'external_message_id' => $externalMessageId,
            'body' => 'Scoped external message',
            'type' => 'text',
            'idempotency_key' => 'idem-'.$externalMessageId,
        ];
    }

    public function test_external_token_scopes_are_stored_without_plain_token_hash_exposure(): void
    {
        $admin = $this->actingAsWithPermissions([
            'chat.webhooks.create',
            'chat.webhooks.manage',
        ]);

        $response = $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Scoped Endpoint',
            'url' => 'https://example.test/scoped-webhook',
            'events' => ['message.created'],
            'scopes' => ['chat.external.messages.send'],
        ])->assertCreated();

        $endpoint = ChatWebhookEndpoint::query()->findOrFail((int) $response->json('data.id'));
        $this->assertSame(['chat.external.messages.send'], data_get($endpoint->metadata, 'token_scopes'));
        $this->assertNotEmpty(data_get($endpoint->metadata, 'token_hash'));
        $this->assertArrayNotHasKey('token_hash', (array) $response->json('data'));
        $this->assertArrayNotHasKey('secret', (array) $response->json('data'));
        $this->assertNotEmpty($response->json('data.plain_token'));
    }

    public function test_unknown_scope_is_rejected(): void
    {
        $this->actingAsWithPermissions(['chat.webhooks.create']);

        $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Bad Scope Endpoint',
            'url' => 'https://example.test/bad-scope',
            'events' => ['message.created'],
            'scopes' => ['chat.external.unknown.scope'],
        ])->assertUnprocessable();
    }

    public function test_empty_scopes_are_rejected_when_explicitly_provided(): void
    {
        $this->actingAsWithPermissions(['chat.webhooks.create']);

        $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Empty Scope Endpoint',
            'url' => 'https://example.test/empty-scope',
            'events' => ['message.created'],
            'scopes' => [],
        ])->assertUnprocessable();
    }

    public function test_external_token_with_send_scope_can_send_message(): void
    {
        $admin = $this->userWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
        ]);

        $conversation = $this->makeExternalConversation($admin);
        $this->addParticipant($conversation, $admin);

        /** @var ExternalChatTokenService $tokenService */
        $tokenService = app(ExternalChatTokenService::class);
        $plainToken = $tokenService->generatePlainToken();
        $tokenHash = $tokenService->hashToken($plainToken);
        ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Send Scope Endpoint',
            'url' => 'https://example.test/send-scope',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $admin->id,
            'metadata' => [
                'token_hash' => $tokenHash,
                'token_hash_algo' => (string) config('chat.external_api.token_hash_algo', 'sha256'),
                'token_scopes' => ['chat.external.messages.send'],
            ],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$plainToken,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, 'scope-send-1'))
            ->assertCreated();
    }

    public function test_external_token_without_send_scope_gets_forbidden(): void
    {
        $admin = $this->userWithPermissions([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
        ]);

        $conversation = $this->makeExternalConversation($admin);
        $this->addParticipant($conversation, $admin);

        /** @var ExternalChatTokenService $tokenService */
        $tokenService = app(ExternalChatTokenService::class);
        $plainToken = $tokenService->generatePlainToken();
        $tokenHash = $tokenService->hashToken($plainToken);
        ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Send Scope Endpoint',
            'url' => 'https://example.test/no-send-scope',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $admin->id,
            'metadata' => [
                'token_hash' => $tokenHash,
                'token_hash_algo' => (string) config('chat.external_api.token_hash_algo', 'sha256'),
                'token_scopes' => ['chat.external.webhooks.view'],
            ],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$plainToken,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/chat/external/messages', $this->validPayload($conversation, 'scope-forbidden-1'))
            ->assertForbidden();
    }

    public function test_invalid_external_token_gets_unauthorized(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer chat_ext_invalid_token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => 1,
            'external_provider' => 'crm-hub',
            'external_message_id' => 'bad-token-1',
            'body' => 'payload',
        ])->assertUnauthorized();
    }
}
