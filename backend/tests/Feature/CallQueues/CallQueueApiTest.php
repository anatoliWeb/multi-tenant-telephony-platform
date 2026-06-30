<?php

namespace Tests\Feature\CallQueues;

use App\Enums\TenantMembershipStatus;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class CallQueueApiTest extends TestCase
{
    use BuildsExtensionFixtures;
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_call_queue_crud_members_pause_resume_options_and_route_testing_are_tenant_scoped(): void
    {
        $tenant = $this->createTenant('call-queues-a');
        $otherTenant = $this->createTenant('call-queues-b');
        $owner = $this->createUser('call-queues-owner');
        $agent = $this->createUser('call-queues-agent');
        $otherUser = $this->createUser('call-queues-other');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);
        $this->createMembership($otherTenant, $otherUser);

        $this->assignTenantPermissions($owner, $tenant, [
            'call_queues.view',
            'call_queues.create',
            'call_queues.update',
            'call_queues.delete',
            'call_queues.manage_members',
            'call_queues.pause_members',
            'call_queues.test_route',
            'extensions.view',
            'users.view',
            'ring_groups.view',
        ]);
        $this->assignTenantPermissions($owner, $otherTenant, ['call_queues.view']);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '4101',
            'label' => 'Queue Desk',
            'assigned_user_id' => $owner->id,
        ]);

        $createResponse = $this->postJson('/api/v1/call-queues', [
            'name' => 'Support Queue',
            'description' => 'Primary support queue.',
            'strategy' => 'sequential',
            'status' => 'active',
            'max_wait_time_seconds' => 600,
            'ring_timeout_seconds' => 25,
            'retry_delay_seconds' => 10,
            'max_attempts' => 4,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.strategy', 'sequential')
            ->assertJsonPath('data.status', 'active');

        $queueId = (int) $createResponse->json('data.id');

        $this->getJson('/api/v1/call-queues/options', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data.extensions')
            ->assertJsonCount(2, 'data.users')
            ->assertJsonCount(6, 'data.strategies')
            ->assertJsonPath('data.statuses.0', 'active');

        $this->postJson("/api/v1/call-queues/{$queueId}/members", [
            'member_type' => 'extension',
            'extension_id' => $extension->id,
            'priority' => 1,
            'penalty' => 0,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.extension.id', $extension->id);

        $memberResponse = $this->postJson("/api/v1/call-queues/{$queueId}/members", [
            'member_type' => 'user',
            'user_id' => $agent->id,
            'priority' => 2,
            'penalty' => 1,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $agent->id);

        $memberId = (int) $memberResponse->json('data.id');

        $this->putJson("/api/v1/call-queues/{$queueId}", [
            'description' => 'Updated support queue.',
            'max_wait_time_seconds' => 900,
            'overflow_destination_type' => 'user',
            'overflow_destination_id' => $owner->id,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated support queue.')
            ->assertJsonPath('data.max_wait_time_seconds', 900)
            ->assertJsonPath('data.overflow_destination_summary', 'user:'.$owner->id);

        $this->putJson("/api/v1/call-queues/{$queueId}/members/{$memberId}", [
            'priority' => 3,
            'penalty' => 2,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.priority', 3)
            ->assertJsonPath('data.penalty', 2);

        $this->postJson("/api/v1/call-queues/{$queueId}/members/{$memberId}/pause", [
            'reason' => 'Break',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.is_paused', true)
            ->assertJsonPath('data.pause_reason', 'Break');

        $this->postJson("/api/v1/call-queues/{$queueId}/members/{$memberId}/resume", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.is_paused', false)
            ->assertJsonPath('data.pause_reason', null);

        $this->getJson("/api/v1/call-queues/{$queueId}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data.members')
            ->assertJsonPath('data.members_count', 2)
            ->assertJsonPath('data.active_members_count', 2);

        $routePlan = $this->postJson("/api/v1/call-queues/{$queueId}/test-route", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $routePlan['eligible_member_count']);
        $this->assertSame('extension', $routePlan['members'][0]['member_type']);
        $this->assertSame('user', $routePlan['members'][1]['member_type']);

        $this->deleteJson("/api/v1/call-queues/{$queueId}/members/{$memberId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $emptyQueueResponse = $this->postJson('/api/v1/call-queues', [
            'name' => 'Overflow Queue',
            'description' => 'Queue without members.',
            'strategy' => 'ring_all',
            'status' => 'active',
            'overflow_destination_type' => 'user',
            'overflow_destination_id' => $owner->id,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated();

        $emptyQueueId = (int) $emptyQueueResponse->json('data.id');

        $overflowPlan = $this->postJson("/api/v1/call-queues/{$emptyQueueId}/test-route", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->json('data');

        $this->assertNull($overflowPlan['primary_member']);
        $this->assertSame('user:'.$owner->id, $overflowPlan['overflow']['summary']);

        $this->deleteJson("/api/v1/call-queues/{$queueId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->getJson("/api/v1/call-queues/{$queueId}", ['X-Tenant-ID' => $otherTenant->id])
            ->assertNotFound();
    }

    public function test_call_queue_access_requires_permissions_and_active_tenant_membership(): void
    {
        $tenant = $this->createTenant('call-queues-access');
        $suspendedTenant = $this->createTenant('call-queues-suspended');
        $user = $this->createUser('call-queues-access-user');
        $suspendedUser = $this->createUser('call-queues-suspended-user');

        $this->createMembership($tenant, $user);
        $this->createMembership($suspendedTenant, $suspendedUser, TenantMembershipStatus::Suspended);

        $queue = CallQueue::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Access Queue',
            'slug' => 'access-queue',
            'description' => null,
            'strategy' => 'ring_all',
            'status' => 'active',
            'max_wait_time_seconds' => 300,
            'ring_timeout_seconds' => 20,
            'retry_delay_seconds' => 5,
            'max_attempts' => 3,
            'music_on_hold' => null,
            'announce_position' => false,
            'announce_estimated_wait' => false,
            'overflow_destination_type' => null,
            'overflow_destination_id' => null,
            'settings' => [],
            'metadata' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/call-queues')
            ->assertForbidden();

        $this->assignTenantPermissions($user, $tenant, ['call_queues.view']);
        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/call-queues')
            ->assertOk();

        Sanctum::actingAs($suspendedUser);
        $this->assignTenantPermissions($suspendedUser, $suspendedTenant, ['call_queues.view']);

        $this->withHeader('X-Tenant-ID', $suspendedTenant->id)
            ->getJson("/api/v1/call-queues/{$queue->id}")
            ->assertForbidden();
    }
}
