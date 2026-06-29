<?php

namespace Tests\Feature\Tenancy\Isolation;

use App\Models\ExternalMessageMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class TenantExternalIntegrationIsolationTest extends TestCase
{
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_external_message_mappings_are_isolated_per_tenant(): void
    {
        $tenantA = $this->createTenant('external-mapping-a');
        $tenantB = $this->createTenant('external-mapping-b');
        $user = $this->actingAsTenantUser($this->createUser('external-mapper'));

        foreach ([$tenantA, $tenantB] as $tenant) {
            $this->createMembership($tenant, $user);
            $this->assignTenantPermissions($user, $tenant, [
                'chat.external_api.send',
                'chat.send',
                'chat.view',
                'chat.conversations.view',
            ]);
        }

        $this->assignPlatformPermissions($user, ['chat.external_api.send']);

        $conversationA = $this->createConversation($tenantA, $user, ['type' => 'external', 'source' => 'api']);
        $conversationB = $this->createConversation($tenantB, $user, ['type' => 'external', 'source' => 'api']);
        $this->addParticipant($conversationA, $user);
        $this->addParticipant($conversationB, $user);

        $payload = [
            'external_provider' => 'crm-hub',
            'external_message_id' => 'shared-ext-001',
            'body' => 'Shared external id across tenants',
            'type' => 'text',
            'idempotency_key' => 'shared-ext-001',
        ];

        $this->withHeader('X-Tenant-ID', $tenantA->id)
            ->postJson('/api/v1/chat/external/messages', $payload + ['conversation_id' => $conversationA->id])
            ->assertCreated();

        $this->withHeader('X-Tenant-ID', $tenantB->id)
            ->postJson('/api/v1/chat/external/messages', $payload + ['conversation_id' => $conversationB->id])
            ->assertCreated();

        $this->assertSame(2, ExternalMessageMapping::query()
            ->where('provider', 'crm-hub')
            ->where('external_id', 'shared-ext-001')
            ->count());
    }

    public function test_external_message_rate_limit_is_isolated_per_explicit_tenant_context(): void
    {
        config()->set('chat.external_api.rate_limit.enabled', true);
        config()->set('chat.external_api.rate_limit.max_attempts', 1);
        config()->set('chat.external_api.rate_limit.decay_seconds', 60);

        $tenantA = $this->createTenant('rate-limit-a');
        $tenantB = $this->createTenant('rate-limit-b');
        $user = $this->actingAsTenantUser($this->createUser('rate-limit-user'));

        foreach ([$tenantA, $tenantB] as $tenant) {
            $this->createMembership($tenant, $user);
            $this->assignTenantPermissions($user, $tenant, [
                'chat.external_api.send',
                'chat.send',
                'chat.view',
                'chat.conversations.view',
            ]);
        }

        $this->assignPlatformPermissions($user, ['chat.external_api.send']);

        $conversationA = $this->createConversation($tenantA, $user, ['type' => 'external', 'source' => 'api']);
        $conversationB = $this->createConversation($tenantB, $user, ['type' => 'external', 'source' => 'api']);
        $this->addParticipant($conversationA, $user);
        $this->addParticipant($conversationB, $user);

        $basePayload = [
            'external_provider' => 'crm-hub',
            'body' => 'Rate limit isolation',
            'type' => 'text',
        ];

        $this->withHeader('X-Tenant-ID', $tenantA->id)
            ->postJson('/api/v1/chat/external/messages', $basePayload + [
                'conversation_id' => $conversationA->id,
                'external_message_id' => 'rate-tenant-a-001',
                'idempotency_key' => 'rate-tenant-a-001',
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-ID', $tenantB->id)
            ->postJson('/api/v1/chat/external/messages', $basePayload + [
                'conversation_id' => $conversationB->id,
                'external_message_id' => 'rate-tenant-b-001',
                'idempotency_key' => 'rate-tenant-b-001',
            ])
            ->assertCreated();
    }

    public function test_webhook_endpoint_listing_and_binding_stay_within_active_tenant(): void
    {
        $tenantA = $this->createTenant('webhook-tenant-a');
        $tenantB = $this->createTenant('webhook-tenant-b');
        $user = $this->actingAsTenantUser($this->createUser('webhook-member'));

        foreach ([$tenantA, $tenantB] as $tenant) {
            $this->createMembership($tenant, $user);
            $this->assignTenantPermissions($user, $tenant, ['chat.webhooks.view', 'chat.webhooks.edit']);
        }

        $endpointA = $this->createWebhookEndpoint($tenantA, $user, ['name' => 'Tenant A endpoint']);
        $endpointB = $this->createWebhookEndpoint($tenantB, $user, ['name' => 'Tenant B endpoint']);

        $this->withHeader('X-Tenant-ID', $tenantB->id)
            ->getJson('/api/v1/chat/webhook-endpoints')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $endpointB->id);

        $this->withHeader('X-Tenant-ID', $tenantB->id)
            ->patchJson("/api/v1/chat/webhook-endpoints/{$endpointA->id}", [
                'name' => 'Cross-tenant edit should fail',
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
