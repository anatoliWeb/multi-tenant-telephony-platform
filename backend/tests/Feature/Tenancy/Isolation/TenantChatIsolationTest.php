<?php

namespace Tests\Feature\Tenancy\Isolation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class TenantChatIsolationTest extends TestCase
{
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_chat_route_model_binding_keeps_conversations_scoped_to_active_tenant(): void
    {
        $tenantA = $this->createTenant('chat-tenant-a');
        $tenantB = $this->createTenant('chat-tenant-b');
        $user = $this->actingAsTenantUser($this->createUser('chat-member'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignPlatformPermissions($user, ['chat.view', 'chat.conversations.view']);
        $this->assignTenantPermissions($user, $tenantA, ['chat.view', 'chat.conversations.view']);
        $this->assignTenantPermissions($user, $tenantB, ['chat.view', 'chat.conversations.view']);

        $conversationA = $this->createConversation($tenantA, $user);
        $conversationB = $this->createConversation($tenantB, $user, ['title' => 'Tenant B conversation']);

        $this->addParticipant($conversationA, $user);
        $this->addParticipant($conversationB, $user);

        $this->withHeader('X-Tenant-ID', $tenantA->id)
            ->getJson("/api/v1/chat/conversations/{$conversationA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversationA->id);

        $this->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson("/api/v1/chat/conversations/{$conversationA->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversationB->id);
    }

    public function test_platform_chat_permissions_do_not_bypass_tenant_chat_view_access(): void
    {
        $tenant = $this->createTenant('platform-view-denied');
        $user = $this->actingAsTenantUser($this->createUser('platform-viewer'));

        $this->createMembership($tenant, $user);
        $this->assignPlatformPermissions($user, ['chat.view', 'chat.conversations.view']);

        $conversation = $this->createConversation($tenant, $user);
        $this->addParticipant($conversation, $user);

        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_platform_external_permissions_do_not_bypass_tenant_chat_send_access(): void
    {
        $tenant = $this->createTenant('platform-external-denied');
        $user = $this->actingAsTenantUser($this->createUser('platform-external'));

        $this->createMembership($tenant, $user);
        $this->assignPlatformPermissions($user, [
            'chat.external_api.send',
            'chat.send',
            'chat.view',
            'chat.conversations.view',
        ]);

        $conversation = $this->createConversation($tenant, $user, [
            'type' => 'external',
            'source' => 'api',
        ]);
        $this->addParticipant($conversation, $user);

        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->postJson('/api/v1/chat/external/messages', [
                'conversation_id' => $conversation->id,
                'external_provider' => 'crm-hub',
                'external_message_id' => 'platform-only-001',
                'body' => 'Should fail without tenant permissions',
                'type' => 'text',
                'idempotency_key' => 'platform-only-001',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to send external chat messages.');
    }
}
