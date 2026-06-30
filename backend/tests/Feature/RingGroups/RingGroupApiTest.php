<?php

namespace Tests\Feature\RingGroups;

use App\Models\RingGroup;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class RingGroupApiTest extends TestCase
{
    use BuildsExtensionFixtures;
    use BuildsTenantIsolationFixtures;
    use DatabaseMigrations;

    public function test_ring_group_crud_members_options_and_route_testing_are_tenant_scoped(): void
    {
        $tenant = $this->createTenant('ring-groups-a');
        $otherTenant = $this->createTenant('ring-groups-b');
        $owner = $this->actingAsTenantUser($this->createUser('ring-groups-owner'));
        $agent = $this->createUser('ring-groups-agent');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);
        $this->createMembership($otherTenant, $owner);

        $this->assignTenantPermissions($owner, $tenant, [
            'ring_groups.view',
            'ring_groups.create',
            'ring_groups.update',
            'ring_groups.delete',
            'ring_groups.manage_members',
            'ring_groups.test_route',
            'extensions.view',
            'users.view',
        ]);
        $this->assignTenantPermissions($owner, $otherTenant, ['ring_groups.view']);

        $extensionA = $this->createExtensionFixture($tenant, $owner, [
            'number' => '2101',
            'label' => 'Sales Desk',
            'assigned_user_id' => $owner->id,
        ]);
        $extensionB = $this->createExtensionFixture($tenant, $owner, [
            'number' => '2102',
            'label' => 'Support Desk',
            'assigned_user_id' => $agent->id,
        ]);

        $createResponse = $this->postJson('/api/v1/ring-groups', [
            'name' => 'Sales Ring Group',
            'description' => 'Primary sales queueless ring group.',
            'strategy' => 'sequential',
            'status' => 'active',
            'ring_timeout_seconds' => 25,
            'max_ring_duration_seconds' => 120,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.strategy', 'sequential')
            ->assertJsonPath('data.status', 'active');

        $ringGroupId = (int) $createResponse->json('data.id');

        $this->getJson('/api/v1/ring-groups/options', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data.extensions')
            ->assertJsonCount(2, 'data.users')
            ->assertJsonPath('data.strategies.0', 'simultaneous')
            ->assertJsonPath('data.statuses.0', 'active');

        $memberOne = $this->postJson("/api/v1/ring-groups/{$ringGroupId}/members", [
            'member_type' => 'extension',
            'extension_id' => $extensionA->id,
            'priority' => 1,
            'delay_seconds' => 0,
            'timeout_seconds' => 20,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.extension.id', $extensionA->id)
            ->json('data.id');

        $memberTwo = $this->postJson("/api/v1/ring-groups/{$ringGroupId}/members", [
            'member_type' => 'user',
            'user_id' => $agent->id,
            'priority' => 2,
            'delay_seconds' => 3,
            'timeout_seconds' => 25,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $agent->id)
            ->json('data.id');

        $this->putJson("/api/v1/ring-groups/{$ringGroupId}", [
            'description' => 'Updated sequential routing group.',
            'ring_timeout_seconds' => 30,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated sequential routing group.')
            ->assertJsonPath('data.ring_timeout_seconds', 30);

        $this->putJson("/api/v1/ring-groups/{$ringGroupId}/members/{$memberTwo}", [
            'priority' => 3,
            'delay_seconds' => 5,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.priority', 3)
            ->assertJsonPath('data.delay_seconds', 5);

        $this->getJson("/api/v1/ring-groups/{$ringGroupId}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data.members')
            ->assertJsonPath('data.members.0.member_type', 'extension');

        $this->getJson("/api/v1/ring-groups/{$ringGroupId}/members", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.user.id', $agent->id);

        $routePlan = $this->postJson("/api/v1/ring-groups/{$ringGroupId}/test-route", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $routePlan['active_member_count']);
        $this->assertSame('extension', $routePlan['members'][0]['member_type']);
        $this->assertSame('user', $routePlan['members'][1]['member_type']);

        $this->deleteJson("/api/v1/ring-groups/{$ringGroupId}/members/{$memberOne}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson("/api/v1/ring-groups/{$ringGroupId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->getJson("/api/v1/ring-groups/{$ringGroupId}", ['X-Tenant-ID' => $otherTenant->id])
            ->assertNotFound();
    }

    public function test_ring_group_access_requires_permissions_and_active_tenant_membership(): void
    {
        $tenant = $this->createTenant('ring-groups-access');
        $user = $this->actingAsTenantUser($this->createUser('ring-groups-access-user'));
        $suspendedUser = $this->actingAsTenantUser($this->createUser('ring-groups-suspended'));

        $this->createMembership($tenant, $user);
        $this->createMembership($tenant, $suspendedUser, \App\Enums\TenantMembershipStatus::Suspended);

        $this->assignTenantPermissions($user, $tenant, ['ring_groups.view']);
        $this->assignTenantPermissions($suspendedUser, $tenant, ['ring_groups.view']);

        $group = RingGroup::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Access Ring Group',
            'slug' => 'access-ring-group',
            'description' => null,
            'strategy' => 'simultaneous',
            'status' => 'active',
            'ring_timeout_seconds' => 20,
            'max_ring_duration_seconds' => 120,
            'settings' => [],
            'metadata' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/ring-groups')
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/ring-groups')
            ->assertOk();

        $this->assignTenantPermissions($user, $tenant, ['ring_groups.view']);
        $this->actingAsTenantUser($user);

        $this->postJson('/api/v1/ring-groups', [
            'name' => 'Denied Ring Group',
        ], ['X-Tenant-ID' => $tenant->id])->assertForbidden();

        Sanctum::actingAs($suspendedUser);
        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson("/api/v1/ring-groups/{$group->id}")
            ->assertForbidden();
    }
}
