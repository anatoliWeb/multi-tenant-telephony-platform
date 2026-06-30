<?php

namespace Tests\Feature\RingGroups;

use App\Enums\RingGroups\RingGroupMemberType;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Services\RingGroups\RingGroupRoutingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class RingGroupRoutingServiceTest extends TestCase
{
    use BuildsExtensionFixtures;
    use BuildsTenantIsolationFixtures;
    use DatabaseMigrations;

    public function test_sequential_strategy_orders_active_members_and_excludes_inactive_and_cross_tenant_targets(): void
    {
        $tenant = $this->createTenant('routing-sequential');
        $otherTenant = $this->createTenant('routing-sequential-other');
        $owner = $this->createUser('routing-owner');
        $agent = $this->createUser('routing-agent');
        $otherUser = $this->createUser('routing-other');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);
        $this->createMembership($otherTenant, $otherUser);

        $activeExtension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '3101',
            'assigned_user_id' => $owner->id,
        ]);
        $crossTenantExtension = $this->createExtensionFixture($otherTenant, $otherUser, [
            'number' => '4101',
            'assigned_user_id' => $otherUser->id,
        ]);

        $ringGroup = RingGroup::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Sequential Routing',
            'slug' => 'sequential-routing',
            'description' => null,
            'strategy' => 'sequential',
            'status' => 'active',
            'ring_timeout_seconds' => 20,
            'max_ring_duration_seconds' => 120,
            'settings' => [],
            'metadata' => [],
        ]);

        RingGroupMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'ring_group_id' => $ringGroup->id,
            'member_type' => RingGroupMemberType::User->value,
            'extension_id' => null,
            'user_id' => $agent->id,
            'priority' => 1,
            'delay_seconds' => 0,
            'timeout_seconds' => 20,
            'is_active' => true,
            'metadata' => [],
        ]);
        RingGroupMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'ring_group_id' => $ringGroup->id,
            'member_type' => RingGroupMemberType::Extension->value,
            'extension_id' => $activeExtension->id,
            'user_id' => null,
            'priority' => 2,
            'delay_seconds' => 3,
            'timeout_seconds' => 25,
            'is_active' => true,
            'metadata' => [],
        ]);
        RingGroupMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'ring_group_id' => $ringGroup->id,
            'member_type' => RingGroupMemberType::Extension->value,
            'extension_id' => $activeExtension->id,
            'user_id' => null,
            'priority' => 0,
            'delay_seconds' => 0,
            'timeout_seconds' => 10,
            'is_active' => false,
            'metadata' => [],
        ]);
        RingGroupMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'ring_group_id' => $ringGroup->id,
            'member_type' => RingGroupMemberType::Extension->value,
            'extension_id' => $crossTenantExtension->id,
            'user_id' => null,
            'priority' => 4,
            'delay_seconds' => 0,
            'timeout_seconds' => 10,
            'is_active' => true,
            'metadata' => [],
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $plan = app(RingGroupRoutingService::class)->resolve($ringGroup);

        $this->assertSame(2, $plan['active_member_count']);
        $this->assertSame(['user', 'extension'], array_column($plan['members'], 'member_type'));
        $this->assertSame($agent->id, $plan['members'][0]['user']['id']);
        $this->assertSame($activeExtension->id, $plan['members'][1]['extension']['id']);
    }

    public function test_simultaneous_strategy_returns_every_active_member_once(): void
    {
        $tenant = $this->createTenant('routing-simultaneous');
        $owner = $this->createUser('routing-simultaneous-owner');
        $agent = $this->createUser('routing-simultaneous-agent');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '3201',
            'assigned_user_id' => $owner->id,
        ]);

        $ringGroup = RingGroup::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Simultaneous Routing',
            'slug' => 'simultaneous-routing',
            'description' => null,
            'strategy' => 'simultaneous',
            'status' => 'active',
            'ring_timeout_seconds' => 20,
            'max_ring_duration_seconds' => 120,
            'settings' => [],
            'metadata' => [],
        ]);

        foreach ([
            [RingGroupMemberType::Extension->value, $extension->id, null, 1, true],
            [RingGroupMemberType::User->value, null, $agent->id, 1, true],
            [RingGroupMemberType::User->value, null, $agent->id, 2, false],
        ] as [$memberType, $extensionId, $userId, $priority, $isActive]) {
            RingGroupMember::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'ring_group_id' => $ringGroup->id,
                'member_type' => $memberType,
                'extension_id' => $extensionId,
                'user_id' => $userId,
                'priority' => $priority,
                'delay_seconds' => 0,
                'timeout_seconds' => 20,
                'is_active' => $isActive,
                'metadata' => [],
            ]);
        }

        app(TenantContext::class)->setTenant($tenant);

        $plan = app(RingGroupRoutingService::class)->resolve($ringGroup);

        $this->assertSame(2, $plan['active_member_count']);
        $this->assertCount(2, $plan['members']);
        $resolvedIds = array_map(static fn (array $member): int => $member['extension'] !== null ? $member['extension']['id'] : $member['user']['id'], $plan['members']);
        $this->assertContains($extension->id, $resolvedIds);
        $this->assertContains($agent->id, $resolvedIds);
    }

    public function test_random_strategy_returns_all_active_targets_and_remains_tenant_scoped(): void
    {
        $tenant = $this->createTenant('routing-random');
        $owner = $this->createUser('routing-random-owner');
        $agent = $this->createUser('routing-random-agent');
        $backup = $this->createUser('routing-random-backup');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);
        $this->createMembership($tenant, $backup);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '3301',
            'assigned_user_id' => $owner->id,
        ]);

        $ringGroup = RingGroup::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Random Routing',
            'slug' => 'random-routing',
            'description' => null,
            'strategy' => 'random',
            'status' => 'active',
            'ring_timeout_seconds' => 20,
            'max_ring_duration_seconds' => 120,
            'settings' => [],
            'metadata' => [],
        ]);

        foreach ([
            [RingGroupMemberType::Extension->value, $extension->id, null, 1],
            [RingGroupMemberType::User->value, null, $agent->id, 2],
            [RingGroupMemberType::User->value, null, $backup->id, 3],
        ] as [$memberType, $extensionId, $userId, $priority]) {
            RingGroupMember::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'ring_group_id' => $ringGroup->id,
                'member_type' => $memberType,
                'extension_id' => $extensionId,
                'user_id' => $userId,
                'priority' => $priority,
                'delay_seconds' => 0,
                'timeout_seconds' => 20,
                'is_active' => true,
                'metadata' => [],
            ]);
        }

        app(TenantContext::class)->setTenant($tenant);

        $plan = app(RingGroupRoutingService::class)->resolve($ringGroup);

        $this->assertSame(3, $plan['active_member_count']);
        $this->assertCount(3, $plan['members']);

        $resolvedIds = array_map(static fn (array $member): int => $member['extension'] !== null ? $member['extension']['id'] : $member['user']['id'], $plan['members']);
        sort($resolvedIds);
        $expectedIds = [$agent->id, $backup->id, $extension->id];
        sort($expectedIds);
        $this->assertSame($expectedIds, $resolvedIds);
    }
}
