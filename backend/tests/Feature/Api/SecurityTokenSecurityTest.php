<?php

namespace Tests\Feature\Api;

use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ExternalChatTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTokenSecurityTest extends TestCase
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

    public function test_internal_token_is_hashed_revokable_and_not_exposed_in_token_list(): void
    {
        $user = User::factory()->create([
            'email' => 'token-security@example.test',
            'password' => bcrypt('Secret123!'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'token-security@example.test',
            'password' => 'Secret123!',
        ])->assertOk();

        $plainToken = (string) $login->json('data.token');
        $this->assertNotSame('', $plainToken);

        $tokenRow = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($plainToken, $tokenRow->token);
        $this->assertSame(64, strlen($tokenRow->token));

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $revokableOwner = $this->actingAsWithPermissions(['tokens.delete']);
        $revokableTokenId = $revokableOwner->createToken('Revokable Security Token')->accessToken->id;
        $this->deleteJson("/api/v1/tokens/{$revokableTokenId}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $revokableTokenId]);

        $tokenOwner = $this->actingAsWithPermissions(['tokens.create', 'tokens.view']);
        Permission::firstOrCreate(['name' => 'users.view']);

        $createToken = $this->postJson('/api/v1/tokens', [
            'name' => 'Token Security Check',
            'scopes' => ['users.view'],
        ])->assertCreated();

        $createPayload = (array) $createToken->json('data');
        $this->assertArrayHasKey('token', $createPayload);
        $this->assertArrayNotHasKey('token_hash', $createPayload);
        $this->assertArrayNotHasKey('authorization', $createPayload);

        $index = $this->getJson('/api/v1/tokens')->assertOk();
        $listedToken = collect($index->json('data'))
            ->firstWhere('id', (int) $createToken->json('data.access_token.id'));
        $this->assertIsArray($listedToken);
        $this->assertArrayNotHasKey('token', $listedToken);
        $this->assertArrayNotHasKey('token_hash', $listedToken);
        $this->assertSame($tokenOwner->id, (int) data_get($listedToken, 'owner.id'));
    }

    public function test_external_chat_token_is_hashed_scoped_and_updates_last_used_without_leakage(): void
    {
        $owner = User::factory()->create();
        $permissionIds = collect([
            'chat.view',
            'chat.conversations.view',
            'chat.send',
        ])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)->all();
        $owner->permissions()->sync($permissionIds);

        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'External token security',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'can_send' => true,
            'can_attach' => true,
            'joined_at' => now(),
        ]);

        /** @var ExternalChatTokenService $externalTokenService */
        $externalTokenService = app(ExternalChatTokenService::class);
        $plainExternalToken = $externalTokenService->generatePlainToken();
        $this->assertNotSame('', $plainExternalToken);
        $this->assertStringStartsWith((string) config('chat.external_api.token_prefix', 'chat_ext_'), $plainExternalToken);

        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'External Token Endpoint',
            'url' => 'https://example.test/ext-token-security',
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $owner->id,
            'metadata' => [
                'token_hash' => $externalTokenService->hashToken($plainExternalToken),
                'token_hash_algo' => (string) config('chat.external_api.token_hash_algo', 'sha256'),
                'token_scopes' => ['chat.external.messages.send'],
            ],
        ]);

        $tokenHash = (string) data_get($endpoint->metadata, 'token_hash', '');
        $this->assertNotSame('', $tokenHash);
        $this->assertNotSame($plainExternalToken, $tokenHash);

        $this->withToken($plainExternalToken)->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => $conversation->id,
            'external_provider' => 'crm-hub',
            'external_message_id' => 'security-token-ok-1',
            'body' => 'Scoped message',
            'type' => 'text',
            'idempotency_key' => 'sec-ok-1',
        ])->assertCreated();

        $endpoint->refresh();
        $this->assertNotNull($endpoint->last_used_at);
        $this->assertNotEmpty(data_get($endpoint->metadata, 'token_last_used_at'));

        ChatWebhookEndpoint::query()->whereKey($endpoint->id)->update([
            'metadata->token_scopes' => ['chat.external.webhooks.view'],
        ]);

        $this->withToken($plainExternalToken)->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => $conversation->id,
            'external_provider' => 'crm-hub',
            'external_message_id' => 'security-token-forbidden-1',
            'body' => 'Forbidden message',
            'type' => 'text',
            'idempotency_key' => 'sec-forbidden-1',
        ])->assertForbidden();

        $invalid = $this->withToken('chat_ext_invalid_plain_token')->postJson('/api/v1/chat/external/messages', [
            'conversation_id' => $conversation->id,
            'external_provider' => 'crm-hub',
            'external_message_id' => 'security-token-unauth-1',
            'body' => 'Unauthorized message',
            'type' => 'text',
            'idempotency_key' => 'sec-unauth-1',
        ])->assertUnauthorized();

        $invalidPayload = mb_strtolower((string) $invalid->getContent());
        $this->assertStringNotContainsString('authorization', $invalidPayload);
        $this->assertStringNotContainsString('token_hash', $invalidPayload);
        $this->assertStringNotContainsString('secret', $invalidPayload);
    }

    public function test_security_docs_and_openapi_docs_do_not_contain_real_token_values(): void
    {
        $securityDoc = (string) file_get_contents(base_path('docs/security.md'));
        $openApiPreparationDoc = (string) file_get_contents(base_path('docs/api/openapi-preparation.md'));

        $this->assertStringContainsString('## Token Security', $securityDoc);
        $this->assertStringContainsString('plain tokens are never stored', mb_strtolower($openApiPreparationDoc));
        $this->assertStringNotContainsString('bearer ey', mb_strtolower($securityDoc));
        $this->assertStringNotContainsString('bearer ey', mb_strtolower($openApiPreparationDoc));
    }
}
