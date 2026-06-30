<?php

namespace App\Services\CallQueues;

use App\Enums\CallQueues\CallQueueMemberType;
use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\CallQueues\CallQueueStrategy;
use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantMembershipStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Events\CallQueues\CallQueueCreated;
use App\Events\CallQueues\CallQueueDeleted;
use App\Events\CallQueues\CallQueueMemberChanged;
use App\Events\CallQueues\CallQueueMemberPaused;
use App\Events\CallQueues\CallQueueMemberResumed;
use App\Events\CallQueues\CallQueueUpdated;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\Extension;
use App\Models\QueueMemberPause;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallQueueService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CallQueueRoutingService $routingService,
    ) {
    }

    public function create(array $payload, User $actor): CallQueue
    {
        return DB::transaction(function () use ($payload, $actor): CallQueue {
            $tenantId = $this->requireTenantId();
            $name = trim((string) $payload['name']);
            $slug = $this->normalizeSlug($payload['slug'] ?? null, $name);
            $this->assertUniqueSlug($tenantId, $slug);
            $this->assertOverflowDestinationValid($tenantId, $payload);

            $queue = CallQueue::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? null,
                'strategy' => $payload['strategy'] ?? CallQueueStrategy::RingAll->value,
                'status' => $payload['status'] ?? CallQueueStatus::Active->value,
                'max_wait_time_seconds' => (int) ($payload['max_wait_time_seconds'] ?? 300),
                'ring_timeout_seconds' => (int) ($payload['ring_timeout_seconds'] ?? 20),
                'retry_delay_seconds' => (int) ($payload['retry_delay_seconds'] ?? 5),
                'max_attempts' => (int) ($payload['max_attempts'] ?? 3),
                'music_on_hold' => $payload['music_on_hold'] ?? null,
                'announce_position' => (bool) ($payload['announce_position'] ?? false),
                'announce_estimated_wait' => (bool) ($payload['announce_estimated_wait'] ?? false),
                'overflow_destination_type' => $payload['overflow_destination_type'] ?? null,
                'overflow_destination_id' => $payload['overflow_destination_id'] ?? null,
                'settings' => $payload['settings'] ?? [],
                'metadata' => $payload['metadata'] ?? [],
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            event(new CallQueueCreated($queue));

            return $queue;
        });
    }

    public function update(CallQueue $queue, array $payload, User $actor): CallQueue
    {
        return DB::transaction(function () use ($queue, $payload, $actor): CallQueue {
            $target = CallQueue::query()->whereKey($queue->getKey())->lockForUpdate()->firstOrFail();
            $tenantId = (string) $target->tenant_id;
            $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : $target->name;
            $slug = $this->normalizeSlug($payload['slug'] ?? $target->slug, $name);
            $this->assertUniqueSlug($tenantId, $slug, $target);
            $this->assertOverflowDestinationValid($tenantId, $payload, $target);

            $target->forceFill([
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? $target->description,
                'strategy' => $payload['strategy'] ?? ($target->strategy?->value ?? $target->strategy),
                'status' => $payload['status'] ?? ($target->status?->value ?? $target->status),
                'max_wait_time_seconds' => $payload['max_wait_time_seconds'] ?? $target->max_wait_time_seconds,
                'ring_timeout_seconds' => $payload['ring_timeout_seconds'] ?? $target->ring_timeout_seconds,
                'retry_delay_seconds' => $payload['retry_delay_seconds'] ?? $target->retry_delay_seconds,
                'max_attempts' => $payload['max_attempts'] ?? $target->max_attempts,
                'music_on_hold' => array_key_exists('music_on_hold', $payload) ? $payload['music_on_hold'] : $target->music_on_hold,
                'announce_position' => array_key_exists('announce_position', $payload) ? (bool) $payload['announce_position'] : $target->announce_position,
                'announce_estimated_wait' => array_key_exists('announce_estimated_wait', $payload) ? (bool) $payload['announce_estimated_wait'] : $target->announce_estimated_wait,
                'overflow_destination_type' => array_key_exists('overflow_destination_type', $payload) ? $payload['overflow_destination_type'] : $target->overflow_destination_type,
                'overflow_destination_id' => array_key_exists('overflow_destination_id', $payload) ? $payload['overflow_destination_id'] : $target->overflow_destination_id,
                'settings' => $payload['settings'] ?? $target->settings ?? [],
                'metadata' => $payload['metadata'] ?? $target->metadata ?? [],
                'updated_by' => $actor->getKey(),
            ])->save();

            event(new CallQueueUpdated($target->fresh()));

            return $target->fresh(['members.extension', 'members.user']);
        });
    }

    public function delete(CallQueue $queue): void
    {
        DB::transaction(function () use ($queue): void {
            event(new CallQueueDeleted($queue));
            $queue->delete();
        });
    }

    public function createMember(CallQueue $queue, array $payload): CallQueueMember
    {
        return DB::transaction(function () use ($queue, $payload): CallQueueMember {
            $this->assertQueueTenant($queue);
            $normalized = $this->normalizeMemberPayload($payload);
            $this->assertMemberTargetEligible($normalized['member_type'], $normalized['extension_id'], $normalized['user_id']);
            $this->assertMemberUniqueness($queue, $normalized['member_type'], $normalized['member_id']);

            $member = CallQueueMember::query()->create(array_merge($normalized, [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $queue->tenant_id,
                'call_queue_id' => $queue->getKey(),
            ]));

            event(new CallQueueMemberChanged($member));

            return $member->fresh(['extension', 'user']);
        });
    }

    public function updateMember(CallQueue $queue, CallQueueMember $member, array $payload): CallQueueMember
    {
        return DB::transaction(function () use ($queue, $member, $payload): CallQueueMember {
            $this->assertQueueTenant($queue);
            $this->assertMemberTenant($queue, $member);
            $normalized = $this->normalizeMemberPayload($payload, $member);
            $this->assertMemberTargetEligible($normalized['member_type'], $normalized['extension_id'], $normalized['user_id']);
            $this->assertMemberUniqueness($queue, $normalized['member_type'], $normalized['member_id'], $member);

            $member->forceFill($normalized)->save();

            event(new CallQueueMemberChanged($member->fresh()));

            return $member->fresh(['extension', 'user']);
        });
    }

    public function deleteMember(CallQueue $queue, CallQueueMember $member): void
    {
        DB::transaction(function () use ($queue, $member): void {
            $this->assertQueueTenant($queue);
            $this->assertMemberTenant($queue, $member);
            event(new CallQueueMemberChanged($member));
            $member->delete();
        });
    }

    public function pauseMember(CallQueue $queue, CallQueueMember $member, User $actor, string $reason): CallQueueMember
    {
        return DB::transaction(function () use ($queue, $member, $actor, $reason): CallQueueMember {
            $this->assertQueueTenant($queue);
            $this->assertMemberTenant($queue, $member);
            if ($member->is_paused) {
                throw new TelephonyConflictException('Call queue member is already paused.');
            }

            // Pause state is tracked both on the current membership row and in
            // the history table so support teams can audit recurring pauses.
            $member->forceFill([
                'is_paused' => true,
                'paused_at' => now(),
                'pause_reason' => $reason,
            ])->save();

            QueueMemberPause::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $queue->tenant_id,
                'call_queue_id' => $queue->getKey(),
                'call_queue_member_id' => $member->getKey(),
                'user_id' => $actor->getKey(),
                'started_at' => now(),
                'reason' => $reason,
                'metadata' => [],
            ]);

            event(new CallQueueMemberPaused($member->fresh(['extension', 'user'])));

            return $member->fresh(['extension', 'user']);
        });
    }

    public function resumeMember(CallQueue $queue, CallQueueMember $member, User $actor): CallQueueMember
    {
        return DB::transaction(function () use ($queue, $member, $actor): CallQueueMember {
            $this->assertQueueTenant($queue);
            $this->assertMemberTenant($queue, $member);
            if (! $member->is_paused) {
                throw new TelephonyConflictException('Call queue member is not paused.');
            }

            // Resume closes the latest open pause row first so the history stays
            // consistent even when a member is toggled multiple times.
            QueueMemberPause::query()
                ->where('tenant_id', $queue->tenant_id)
                ->where('call_queue_id', $queue->getKey())
                ->where('call_queue_member_id', $member->getKey())
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first()?->update([
                    'ended_at' => now(),
                    'updated_at' => now(),
                ]);

            $member->forceFill([
                'is_paused' => false,
                'paused_at' => null,
                'pause_reason' => null,
            ])->save();

            event(new CallQueueMemberResumed($member->fresh(['extension', 'user'])));

            return $member->fresh(['extension', 'user']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function testRoute(CallQueue $queue): array
    {
        return $this->routingService->resolve($queue->fresh(['members.extension', 'members.user']));
    }

    private function requireTenantId(): string
    {
        return (string) $this->tenantContext->requireTenant()->getKey();
    }

    private function assertQueueTenant(CallQueue $queue): void
    {
        if ((string) $queue->tenant_id !== (string) $this->tenantContext->requireTenant()->getKey()) {
            abort(404, 'Call queue not found.');
        }
    }

    private function assertMemberTenant(CallQueue $queue, CallQueueMember $member): void
    {
        if ((string) $member->tenant_id !== (string) $queue->tenant_id || (string) $member->call_queue_id !== (string) $queue->getKey()) {
            abort(404, 'Call queue member not found.');
        }
    }

    private function normalizeSlug(mixed $slug, string $name): string
    {
        $candidate = trim((string) $slug);

        return $candidate !== '' ? Str::slug($candidate) : Str::slug($name);
    }

    private function assertUniqueSlug(string $tenantId, string $slug, ?CallQueue $queue = null): void
    {
        $query = CallQueue::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);

        if ($queue instanceof CallQueue) {
            $query->where('id', '!=', $queue->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('A call queue with this slug already exists in the active tenant.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertOverflowDestinationValid(string $tenantId, array $payload, ?CallQueue $queue = null): void
    {
        $type = array_key_exists('overflow_destination_type', $payload)
            ? $payload['overflow_destination_type']
            : $queue?->overflow_destination_type;
        $destinationId = array_key_exists('overflow_destination_id', $payload)
            ? $payload['overflow_destination_id']
            : $queue?->overflow_destination_id;

        if (! is_string($type) || $type === '' || ! $destinationId) {
            return;
        }

        if ($type === 'extension') {
            $exists = Extension::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->where('status', ExtensionStatus::Active->value)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Overflow destination extension must belong to the active tenant and be active.');
            }

            return;
        }

        if ($type === 'user') {
            $exists = User::query()
                ->whereKey($destinationId)
                ->whereHas('tenantMemberships', fn ($builder) => $builder
                    ->where('tenant_id', $tenantId)
                    ->where('status', TenantMembershipStatus::Active->value))
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Overflow destination user must belong to the active tenant and have an active membership.');
            }

            return;
        }

        if ($type === 'ring_group') {
            $exists = RingGroup::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->where('status', 'active')
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Overflow destination ring group must belong to the active tenant and be active.');
            }

            return;
        }

        if ($type === 'queue') {
            $exists = CallQueue::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Overflow destination queue must belong to the active tenant.');
            }

            if ($queue instanceof CallQueue && (string) $queue->getKey() === (string) $destinationId) {
                throw new TelephonyConflictException('Overflow destination cannot point to the queue itself.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMemberPayload(array $payload, ?CallQueueMember $existing = null): array
    {
        $memberType = (string) ($payload['member_type'] ?? $existing?->member_type?->value ?? $existing?->member_type);
        $priority = (int) ($payload['priority'] ?? $existing?->priority ?? 1);
        $penalty = (int) ($payload['penalty'] ?? $existing?->penalty ?? 0);
        $isActive = array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : (bool) ($existing?->is_active ?? true);
        $isPaused = array_key_exists('is_paused', $payload) ? (bool) $payload['is_paused'] : (bool) ($existing?->is_paused ?? false);
        $memberId = $memberType === CallQueueMemberType::Extension->value
            ? (int) ($payload['extension_id'] ?? $existing?->extension_id ?? 0)
            : (int) ($payload['user_id'] ?? $existing?->user_id ?? 0);

        return [
            'member_type' => $memberType,
            'member_id' => $memberId,
            'extension_id' => $memberType === CallQueueMemberType::Extension->value ? $memberId : null,
            'user_id' => $memberType === CallQueueMemberType::User->value ? $memberId : null,
            'priority' => $priority,
            'penalty' => $penalty,
            'is_active' => $isActive,
            'is_paused' => $isPaused,
            'paused_at' => $isPaused ? ($existing?->paused_at ?? now()) : null,
            'pause_reason' => $payload['pause_reason'] ?? $existing?->pause_reason ?? null,
            'last_call_at' => $payload['last_call_at'] ?? $existing?->last_call_at ?? null,
            'metadata' => $payload['metadata'] ?? $existing?->metadata ?? [],
        ];
    }

    private function assertMemberTargetEligible(string $memberType, ?int $extensionId, ?int $userId): void
    {
        $tenantId = $this->requireTenantId();

        if ($memberType === CallQueueMemberType::Extension->value) {
            $exists = Extension::query()
                ->forTenant($tenantId)
                ->whereKey($extensionId)
                ->where('status', ExtensionStatus::Active->value)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Call queue member extension must belong to the active tenant and be active.');
            }
        }

        if ($memberType === CallQueueMemberType::User->value) {
            $exists = User::query()
                ->whereKey($userId)
                ->whereHas('tenantMemberships', fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', TenantMembershipStatus::Active->value))
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Call queue member user must belong to the active tenant and have an active membership.');
            }
        }
    }

    private function assertMemberUniqueness(CallQueue $queue, string $memberType, ?int $memberId, ?CallQueueMember $ignore = null): void
    {
        $query = CallQueueMember::query()
            ->where('tenant_id', $queue->tenant_id)
            ->where('call_queue_id', $queue->getKey())
            ->where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->where('is_active', true);

        if ($ignore instanceof CallQueueMember) {
            $query->where('id', '!=', $ignore->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('This active member already exists in the call queue.');
        }
    }
}
