<?php

namespace Tests\Feature\Chat;

use App\Models\ChatWebhookEndpoint;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatWebhookSecretRotationService;
use App\Services\Chat\ChatWebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatWebhookSecretRotationTest extends TestCase
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

    private function makeEndpoint(string $secret = 'initial-secret'): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Rotation Endpoint',
            'url' => 'https://example.test/rotation',
            'secret' => $secret,
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => User::factory()->create()->id,
            'metadata' => ['token_hash' => 'hash'],
        ]);
    }

    public function test_chat_webhook_secret_rotation_foundation(): void
    {
        config()->set('chat.webhooks.secret_rotation_grace_seconds', 3600);
        config()->set('chat.webhooks.endpoint_management_rate_limit.max_attempts', 10);
        config()->set('chat.webhooks.endpoint_management_rate_limit.decay_seconds', 60);

        $admin = $this->actingAsWithPermissions(['chat.webhooks.manage']);
        $endpoint = $this->makeEndpoint('secret-v1');

        $rotateResponse = $this->postJson("/api/v1/chat/webhook-endpoints/{$endpoint->id}/rotate-secret")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $endpoint->id);

        $payload = (array) $rotateResponse->json('data');
        $this->assertArrayHasKey('plain_secret', $payload);
        $this->assertArrayHasKey('rotated_at', $payload);
        $this->assertArrayHasKey('previous_secret_expires_at', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('token_hash', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertStringNotContainsString('secret-v1', (string) $rotateResponse->getContent());

        $newSecret = (string) $payload['plain_secret'];
        $this->assertNotSame('secret-v1', $newSecret);

        $endpoint->refresh();
        $this->assertSame($newSecret, $endpoint->secret);
        $this->assertNotNull(data_get($endpoint->metadata, 'webhook_secret_rotation.rotated_at'));
        $this->assertNotNull(data_get($endpoint->metadata, 'webhook_secret_rotation.previous_secret_expires_at'));
        $this->assertNotNull(data_get($endpoint->metadata, 'webhook_secret_rotation.previous_secret_encrypted'));

        // List response never exposes rotated plain secret.
        $this->getJson('/api/v1/chat/webhook-endpoints')
            ->assertOk()
            ->assertJsonMissing(['plain_secret' => $newSecret]);

        $signing = app(ChatWebhookSigningService::class);
        $rotation = app(ChatWebhookSecretRotationService::class);
        $body = json_encode(['event' => 'message.created', 'message_id' => 1], JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;

        $signedNew = $signing->signPayload($body, $newSecret, $timestamp);
        $this->assertTrue($rotation->verifyWithRotation($endpoint, $body, $signedNew['signature'], $timestamp));

        $signedOld = $signing->signPayload($body, 'secret-v1', $timestamp);
        $this->assertTrue($rotation->verifyWithRotation($endpoint, $body, $signedOld['signature'], $timestamp));

        $this->travel(3700)->seconds();
        $endpoint->refresh();
        $this->assertFalse($rotation->verifyWithRotation($endpoint, $body, $signedOld['signature'], $timestamp));
        $this->travelBack();

        $nonAdmin = $this->actingAsWithPermissions(['chat.webhooks.view']);
        $this->assertNotSame($admin->id, $nonAdmin->id);
        $this->postJson("/api/v1/chat/webhook-endpoints/{$endpoint->id}/rotate-secret")
            ->assertForbidden();

        config()->set('chat.webhooks.endpoint_management_rate_limit.max_attempts', 2);
        config()->set('chat.webhooks.endpoint_management_rate_limit.decay_seconds', 60);
        $this->actingAsWithPermissions(['chat.webhooks.manage']);
        $ep2 = $this->makeEndpoint('secret-v2');
        $this->postJson("/api/v1/chat/webhook-endpoints/{$ep2->id}/rotate-secret")->assertOk();
        $this->postJson("/api/v1/chat/webhook-endpoints/{$ep2->id}/rotate-secret")->assertOk();
        $this->postJson("/api/v1/chat/webhook-endpoints/{$ep2->id}/rotate-secret")->assertStatus(429);
    }
}

