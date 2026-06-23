<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatMessageSendRateLimitTest extends TestCase
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

    private function makeConversation(?User $owner = null): Conversation
    {
        $owner ??= User::factory()->create();

        return Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Rate limit',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'history_import_from_message_id' => null,
            'history_import_from_at' => null,
            'last_message_id' => null,
            'last_message_at' => null,
            'metadata' => null,
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
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => false,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => false,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'joined_at' => now(),
        ]);
    }

    public function test_chat_message_send_rate_limit_foundation(): void
    {
        config()->set('chat.message_sending_rate_limit.enabled', true);
        config()->set('chat.message_sending_rate_limit.max_attempts', 2);
        config()->set('chat.message_sending_rate_limit.decay_seconds', 60);

        $conversation = $this->makeConversation();
        $sender = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $sender);

        // 1) under limit send returns success
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['body' => 'one'])
            ->assertCreated();
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['body' => 'two'])
            ->assertCreated();

        $this->assertSame(2, Message::query()->where('conversation_id', $conversation->id)->count());

        // 2) over limit returns 429 and 3) no extra message is created
        $blockedBody = 'three-blocked';
        $over = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['body' => $blockedBody]);
        $over->assertStatus(429);
        $this->assertSame(2, Message::query()->where('conversation_id', $conversation->id)->count());

        // 8) 429 response does not expose sensitive fields
        $payload = (string) $over->getContent();
        $this->assertStringNotContainsString('token', $payload);
        $this->assertStringNotContainsString('secret', $payload);
        $this->assertStringNotContainsString($blockedBody, $payload);

        // 4) different users have isolated limits
        $otherUser = $this->actingAsWithPermissions(['chat.send', 'chat.view', 'chat.conversations.view']);
        $this->addParticipant($conversation, $otherUser);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['body' => 'other-user'])
            ->assertCreated();

        // 5) different conversations have isolated limits for same user
        Sanctum::actingAs($sender);
        $secondConversation = $this->makeConversation();
        $this->addParticipant($secondConversation, $sender);
        $this->postJson("/api/v1/chat/conversations/{$secondConversation->id}/messages", ['body' => 'other-conversation'])
            ->assertCreated();

        // 6) config values respected by limiter contract
        $limiter = RateLimiter::limiter('chat-message-send');
        $this->assertIsCallable($limiter);
        $request = Request::create("/api/v1/chat/conversations/{$conversation->id}/messages", 'POST');
        $request->setUserResolver(fn () => $sender);
        $request->setRouteResolver(fn () => new class($conversation->id) {
            public function __construct(private readonly int $conversationId)
            {
            }

            public function parameter(string $name): mixed
            {
                return $name === 'conversation' ? $this->conversationId : null;
            }
        });
        $limit = $limiter($request);
        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(2, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);

        // 7) enabled=false disables limiter
        config()->set('chat.message_sending_rate_limit.enabled', false);
        $limitDisabled = $limiter($request);
        $this->assertInstanceOf(Limit::class, $limitDisabled);

        Sanctum::actingAs($sender);
        $thirdConversation = $this->makeConversation();
        $this->addParticipant($thirdConversation, $sender);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson("/api/v1/chat/conversations/{$thirdConversation->id}/messages", [
                'body' => 'no-limit-'.$i,
            ])->assertCreated();
        }

        // 9) external API endpoint still uses its own limiter middleware
        $externalRoute = app('router')->getRoutes()->getByName('api.v1.chat.external.messages.store');
        $this->assertNotNull($externalRoute);
        $this->assertContains('throttle:chat-external-api', $externalRoute->gatherMiddleware());
    }
}
