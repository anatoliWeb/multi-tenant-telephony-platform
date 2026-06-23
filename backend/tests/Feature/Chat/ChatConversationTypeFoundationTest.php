<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Permission;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\Chat\ChatConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatConversationTypeFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();
        $user->permissions()->sync($permissionIds);

        return $user;
    }

    private function actingAsWithPermissions(array $permissions): User
    {
        $user = $this->makeUserWithPermissions($permissions);
        Sanctum::actingAs($user);

        return $user;
    }

    private function makeConversation(array $overrides = []): Conversation
    {
        $owner = $overrides['owner'] ?? User::factory()->create();
        unset($overrides['owner']);

        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Type foundation',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'metadata' => null,
        ], $overrides));
    }

    private function addParticipant(Conversation $conversation, User $user, array $overrides = []): ConversationParticipant
    {
        return ConversationParticipant::query()->create(array_merge([
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
        ], $overrides));
    }

    public function test_chat_conversation_type_foundation(): void
    {
        /** @var ChatConversationService $service */
        $service = app(ChatConversationService::class);
        /** @var ChatAccessService $access */
        $access = app(ChatAccessService::class);

        $creator = $this->makeUserWithPermissions([
            'chat.create',
            'chat.conversations.create',
            'chat.view',
            'chat.conversations.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',
            'chat.admin.moderate',
        ]);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $supportConversation = $service->createSupportConversation($creator, [$u1->id, $u2->id], [
            'title' => 'Support channel',
            'support_user_ids' => [$u1->id],
        ]);
        $this->assertSame('support', $supportConversation->type);
        $this->assertSame('internal', $supportConversation->source);
        $this->assertSame('private', $supportConversation->visibility);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $supportConversation->id,
            'user_id' => $u1->id,
            'role' => 'support',
        ]);

        $externalConversation = $service->createExternalConversation($creator, [$u1->id], [
            'title' => 'External channel',
        ]);
        $this->assertSame('external', $externalConversation->type);
        $this->assertSame('api', $externalConversation->source);
        $this->assertSame('private', $externalConversation->visibility);

        $nonAdmin = $this->makeUserWithPermissions(['chat.create', 'chat.conversations.create']);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->createSystemConversation($nonAdmin, [$u1->id], ['title' => 'forbidden']);
    }

    public function test_system_conversation_and_admin_participant_role_rules(): void
    {
        /** @var ChatConversationService $service */
        $service = app(ChatConversationService::class);
        /** @var ChatAccessService $access */
        $access = app(ChatAccessService::class);

        $adminActor = $this->makeUserWithPermissions([
            'chat.create',
            'chat.conversations.create',
            'chat.view',
            'chat.conversations.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',
            'chat.admin.view',
            'chat.admin.moderate',
        ]);
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();

        $systemConversation = $service->createSystemConversation($adminActor, [$memberA->id], [
            'title' => 'System channel',
        ]);
        $this->assertSame('system', $systemConversation->type);
        $this->assertSame('system', $systemConversation->source);
        $this->assertSame('private', $systemConversation->visibility);

        $adminParticipant = $service->addParticipant($adminActor, $systemConversation, $memberB->id, [
            'role' => 'admin',
            'can_invite' => true,
            'can_remove' => true,
            'can_manage' => true,
            'can_moderate' => true,
        ]);
        $this->assertSame('admin', $adminParticipant->role);

        $memberB->permissions()->sync([
            Permission::firstOrCreate(['name' => 'chat.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.conversations.view'])->id,
            Permission::firstOrCreate(['name' => 'chat.participants.add'])->id,
            Permission::firstOrCreate(['name' => 'chat.participants.remove'])->id,
            Permission::firstOrCreate(['name' => 'chat.participants.manage'])->id,
            Permission::firstOrCreate(['name' => 'chat.admin.moderate'])->id,
        ]);

        $freshSystemConversation = Conversation::query()->findOrFail($systemConversation->id);
        $this->assertTrue($access->canInvite($memberB, $freshSystemConversation));
        $this->assertTrue($access->canRemoveParticipant($memberB, $freshSystemConversation));
        $this->assertTrue($access->canManage($memberB, $freshSystemConversation));
        $this->assertTrue($access->canModerate($memberB, $freshSystemConversation));

        $createdByAdminParticipant = $service->addParticipant($memberB, $freshSystemConversation, User::factory()->create()->id);
        $this->assertSame($freshSystemConversation->id, $createdByAdminParticipant->conversation_id);

        $viewer = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->addParticipant($freshSystemConversation, $viewer);
        $this->getJson("/api/v1/chat/conversations/{$freshSystemConversation->id}")->assertOk();

        $outsider = $this->actingAsWithPermissions(['chat.view', 'chat.conversations.view']);
        $this->getJson("/api/v1/chat/conversations/{$freshSystemConversation->id}")->assertStatus(404);
    }
}

