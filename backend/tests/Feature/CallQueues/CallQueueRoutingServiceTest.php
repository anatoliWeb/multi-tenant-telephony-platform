<?php

namespace Tests\Feature\CallQueues;

use App\Enums\CallQueues\CallQueueMemberType;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\Extension;
use App\Services\CallQueues\CallQueueRoutingService;
use App\Services\Tenancy\TenantContext;
use App\Enums\CallQueues\CallQueueStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class CallQueueRoutingServiceTest extends TestCase
{
    use BuildsExtensionFixtures;
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_ring_all_returns_all_eligible_members_and_excludes_paused_or_inactive_targets(): void
    {
        $tenant = $this->createTenant('call-queues-routing-ring-all');
        $owner = $this->createUser('call-queues-routing-owner');
        $agent = $this->createUser('call-queues-routing-agent');
        $otherTenant = $this->createTenant('call-queues-routing-other');
        $otherUser = $this->createUser('call-queues-routing-other-user');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);
        $this->createMembership($otherTenant, $otherUser);

        $activeExtension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '5101',
            'assigned_user_id' => $owner->id,
        ]);
        $pausedExtension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '5102',
            'assigned_user_id' => $owner->id,
        ]);

        $queue = $this->createQueue($tenant, 'ring-all-queue', CallQueueStrategy::RingAll->value);

        CallQueueMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'call_queue_id' => $queue->id,
            'member_type' => CallQueueMemberType::User->value,
            'member_id' => $owner->id,
            'extension_id' => null,
            'user_id' => $owner->id,
            'priority' => 1,
            'penalty' => 0,
            'is_active' => true,
            'is_paused' => false,
            'metadata' => [],
        ]);
        CallQueueMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'call_queue_id' => $queue->id,
            'member_type' => CallQueueMemberType::Extension->value,
            'member_id' => $activeExtension->id,
            'extension_id' => $activeExtension->id,
            'user_id' => null,
            'priority' => 2,
            'penalty' => 0,
            'is_active' => true,
            'is_paused' => false,
            'metadata' => [],
        ]);
        CallQueueMember::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'call_queue_id' => $queue->id,
            'member_type' => CallQueueMemberType::Extension->value,
            'member_id' => $pausedExtension->id,
            'extension_id' => $pausedExtension->id,
            'user_id' => null,
            'priority' => 3,
            'penalty' => 0,
            'is_active' => true,
            'is_paused' => true,
            'paused_at' => now(),
            'pause_reason' => 'Pause for test',
            'metadata' => [],
        ]);

        app(TenantContext::class)->setTenant($tenant);
        $plan = app(CallQueueRoutingService::class)->resolve($queue);

        $this->assertSame(2, $plan['eligible_member_count']);
        $this->assertSame(['user', 'extension'], array_column($plan['members'], 'member_type'));
        $this->assertNull($plan['overflow']);
        $this->assertSame($owner->id, $plan['members'][0]['user']['id']);
        $this->assertSame($activeExtension->id, $plan['members'][1]['extension']['id']);
    }

    public function test_sequential_round_robin_and_random_are_deterministic(): void
    {
        $tenant = $this->createTenant('call-queues-routing-deterministic');
        $owner = $this->createUser('call-queues-routing-deterministic-owner');
        $agent = $this->createUser('call-queues-routing-deterministic-agent');

        $this->createMembership($tenant, $owner);
        $this->createMembership($tenant, $agent);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '5201',
            'assigned_user_id' => $owner->id,
        ]);

        $sequential = $this->createQueue($tenant, 'sequential-queue', CallQueueStrategy::Sequential->value);
        $roundRobin = $this->createQueue($tenant, 'round-robin-queue', CallQueueStrategy::RoundRobin->value);
        $random = $this->createQueue($tenant, 'random-queue', CallQueueStrategy::Random->value);

        foreach ([$sequential, $roundRobin, $random] as $queue) {
            CallQueueMember::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'call_queue_id' => $queue->id,
                'member_type' => CallQueueMemberType::Extension->value,
                'member_id' => $extension->id,
                'extension_id' => $extension->id,
                'user_id' => null,
                'priority' => 2,
                'penalty' => 1,
                'is_active' => true,
                'is_paused' => false,
                'metadata' => [],
            ]);
            CallQueueMember::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'call_queue_id' => $queue->id,
                'member_type' => CallQueueMemberType::User->value,
                'member_id' => $agent->id,
                'extension_id' => null,
                'user_id' => $agent->id,
                'priority' => 1,
                'penalty' => 0,
                'is_active' => true,
                'is_paused' => false,
                'last_call_at' => now()->subMinutes($queue->id),
                'metadata' => [],
            ]);
        }

        app(TenantContext::class)->setTenant($tenant);

        $sequentialPlan = app(CallQueueRoutingService::class)->resolve($sequential);
        $roundRobinPlan = app(CallQueueRoutingService::class)->resolve($roundRobin);
        $randomPlan = app(CallQueueRoutingService::class)->resolve($random);

        $this->assertSame(['user', 'extension'], array_column($sequentialPlan['members'], 'member_type'));
        $this->assertSame(['user', 'extension'], array_column($roundRobinPlan['members'], 'member_type'));
        $this->assertCount(2, $randomPlan['members']);
        $this->assertSame($agent->id, $roundRobinPlan['primary_member']['user']['id']);
    }

    public function test_overflow_is_returned_when_no_members_are_eligible(): void
    {
        $tenant = $this->createTenant('call-queues-routing-overflow');
        $owner = $this->createUser('call-queues-routing-overflow-owner');

        $this->createMembership($tenant, $owner);

        $queue = $this->createQueue($tenant, 'overflow-queue', CallQueueStrategy::RingAll->value, [
            'overflow_destination_type' => 'user',
            'overflow_destination_id' => $owner->id,
        ]);

        app(TenantContext::class)->setTenant($tenant);
        $plan = app(CallQueueRoutingService::class)->resolve($queue);

        $this->assertSame(0, $plan['eligible_member_count']);
        $this->assertNull($plan['primary_member']);
        $this->assertSame('user:'.$owner->id, $plan['overflow']['summary']);
    }

    private function createQueue(\App\Models\Tenant $tenant, string $slug, string $strategy, array $overrides = []): CallQueue
    {
        return CallQueue::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'description' => null,
            'strategy' => $strategy,
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
        ], $overrides));
    }
}
