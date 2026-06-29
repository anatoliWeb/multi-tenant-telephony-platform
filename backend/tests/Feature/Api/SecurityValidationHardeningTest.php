<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\Chat\ChatMessageController;
use App\Http\Requests\Api\IncomingChatWebhookRequest;
use App\Http\Requests\Api\SendExternalChatMessageRequest;
use App\Http\Requests\Api\UploadChatAttachmentRequest;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class SecurityValidationHardeningTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    private function makeConversationWithParticipant(User $owner): Conversation
    {
        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Validation hardening',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'internal',
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
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ]);

        return $conversation;
    }

    public function test_validation_hardening_for_critical_endpoints(): void
    {
        $auth = $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
        $this->assertFalse(isset($auth['trace']));
        $authPayload = (string) $auth->getContent();
        $this->assertStringNotContainsString('wrongpassword123!', mb_strtolower($authPayload));
        $this->assertStringNotContainsString('bearer ', mb_strtolower($authPayload));
        $this->assertStringNotContainsString('app-secret', mb_strtolower($authPayload));

        $user = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $conversation = $this->makeConversationWithParticipant($user);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => str_repeat('x', 10001),
            'type' => 'invalid',
        ])->assertStatus(422);

        $manager = $this->actingAsWithPermissions(['chat.webhooks.create']);
        $invalidWebhook = $this->postJson('/api/v1/chat/webhook-endpoints', [
            'name' => 'Invalid URL',
            'url' => 'not-a-url',
            'events' => ['message.created'],
        ])->assertStatus(422);
        $invalidPayload = (string) $invalidWebhook->getContent();
        $this->assertStringNotContainsString('secret', mb_strtolower($invalidPayload));
        $this->assertStringNotContainsString('token', mb_strtolower($invalidPayload));
        $this->assertStringNotContainsString('storage', mb_strtolower($invalidPayload));

        $endpoint = ChatWebhookEndpoint::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Incoming',
            'url' => 'https://example.com/webhook',
            'secret' => 'super-secret',
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $manager->id,
        ]);

        $invalidSignature = $this->postJson("/api/v1/chat/external/webhooks/{$endpoint->uuid}", [
            'event' => 'message.created',
            'conversation_id' => $conversation->id,
            'external_provider' => 'provider',
            'external_message_id' => 'msg-1',
            'body' => 'Hello',
        ], [
            'X-Chat-Signature' => 'invalid-signature',
            'X-Chat-Timestamp' => (string) now()->timestamp,
        ])->assertStatus(403);
        $invalidSignaturePayload = mb_strtolower((string) $invalidSignature->getContent());
        $this->assertStringNotContainsString('super-secret', $invalidSignaturePayload);
        $this->assertStringNotContainsString('raw_payload', $invalidSignaturePayload);
    }

    public function test_request_rules_and_form_request_usage_are_hardened(): void
    {
        $externalValidator = Validator::make([
            'conversation_id' => 1,
            'external_provider' => 'bad provider space',
            'external_message_id' => 'bad space id',
            'body' => 'ok',
        ], (new SendExternalChatMessageRequest())->rules());
        $this->assertTrue($externalValidator->fails());
        $this->assertArrayHasKey('external_provider', $externalValidator->errors()->toArray());
        $this->assertArrayHasKey('external_message_id', $externalValidator->errors()->toArray());

        $incomingValidator = Validator::make([
            'event' => 'message.created',
            'conversation_id' => 1,
            'external_provider' => 'provider',
            'external_message_id' => 'msg-1',
            'body' => 'body',
            'metadata' => array_fill(0, 51, 'x'),
        ], (new IncomingChatWebhookRequest())->rules());
        $this->assertTrue($incomingValidator->fails());
        $this->assertArrayHasKey('metadata', $incomingValidator->errors()->toArray());

        $uploadRules = (new UploadChatAttachmentRequest())->rules();
        $this->assertArrayHasKey('file', $uploadRules);
        $this->assertStringContainsString('max:', implode('|', $uploadRules['file']));

        $authTokenParam = (new ReflectionMethod(AuthController::class, 'token'))->getParameters()[0]->getType()?->getName();
        $authSessionParam = (new ReflectionMethod(AuthController::class, 'sessionLogin'))->getParameters()[0]->getType()?->getName();
        $chatStoreParam = (new ReflectionMethod(ChatMessageController::class, 'store'))->getParameters()[0]->getType()?->getName();
        $this->assertSame('App\\Http\\Requests\\Api\\AuthTokenLoginRequest', $authTokenParam);
        $this->assertSame('App\\Http\\Requests\\Api\\AuthSessionLoginRequest', $authSessionParam);
        $this->assertSame('App\\Http\\Requests\\Api\\SendChatMessageRequest', $chatStoreParam);
    }
}
