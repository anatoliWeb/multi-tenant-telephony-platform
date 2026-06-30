<?php

namespace App\Services\CallQueues;

use App\Enums\CallQueues\CallQueueMemberType;
use App\Enums\CallQueues\CallQueueStrategy;
use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantMembershipStatus;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\Extension;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class CallQueueRoutingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Resolve a tenant-safe routing plan for a call queue.
     *
     * WHY:
     * This slice prepares configuration and deterministic route selection
     * without placing real calls yet, so PBX adapters can plug in later.
     *
     * @return array<string, mixed>
     */
    public function resolve(CallQueue $queue): array
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $queue->tenant_id !== (string) $tenant->getKey()) {
            abort(404, 'Call queue not found.');
        }

        $queue->loadMissing(['members.extension', 'members.user']);
        $eligible = $queue->members
            ->filter(fn (CallQueueMember $member): bool => $this->isEligible($member))
            ->values();

        $ordered = $this->orderMembers($queue, $eligible);

        return [
            'queue' => [
                'id' => $queue->id,
                'uuid' => $queue->uuid,
                'name' => $queue->name,
                'strategy' => $queue->strategy?->value ?? $queue->strategy,
                'status' => $queue->status?->value ?? $queue->status,
            ],
            'resolved_at' => now()->toISOString(),
            'eligible_member_count' => $eligible->count(),
            'members' => $ordered->map(fn (CallQueueMember $member): array => $this->serializeMember($member))->values()->all(),
            'primary_member' => $ordered->isNotEmpty() ? $this->serializeMember($ordered->first()) : null,
            'overflow' => $ordered->isEmpty() ? $this->serializeOverflow($queue) : null,
            'notes' => $this->notesForStrategy($queue->strategy instanceof CallQueueStrategy ? $queue->strategy : CallQueueStrategy::RingAll),
        ];
    }

    private function isEligible(CallQueueMember $member): bool
    {
        $tenant = $this->tenantContext->requireTenant();

        if ((string) $member->tenant_id !== (string) $tenant->getKey()) {
            return false;
        }

        if (! $member->is_active || $member->is_paused) {
            return false;
        }

        if ($member->member_type === CallQueueMemberType::Extension) {
            $extension = $member->extension;
            return $extension instanceof Extension
                && (string) $extension->tenant_id === (string) $tenant->getKey()
                && ($extension->status?->value ?? $extension->status) === ExtensionStatus::Active->value;
        }

        if ($member->member_type === CallQueueMemberType::User) {
            $user = $member->user;
            return $user instanceof User
                && $user->tenantMemberships()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('status', TenantMembershipStatus::Active->value)
                    ->exists();
        }

        return false;
    }

    /**
     * @param \Illuminate\Support\Collection<int, CallQueueMember> $members
     * @return \Illuminate\Support\Collection<int, CallQueueMember>
     */
    private function orderMembers(CallQueue $queue, $members)
    {
        $strategy = $queue->strategy instanceof CallQueueStrategy
            ? $queue->strategy
            : CallQueueStrategy::tryFrom((string) $queue->strategy) ?? CallQueueStrategy::RingAll;

        return match ($strategy) {
            CallQueueStrategy::RingAll => $members->sortBy(fn (CallQueueMember $member): string => $this->priorityKey($member))->values(),
            CallQueueStrategy::RoundRobin => $members->sortBy(fn (CallQueueMember $member): string => $this->roundRobinKey($member))->values(),
            CallQueueStrategy::Sequential => $members->sortBy(fn (CallQueueMember $member): string => $this->priorityKey($member))->values(),
            CallQueueStrategy::Random => $members->sortBy(fn (CallQueueMember $member): string => $this->randomKey($queue, $member))->values(),
            CallQueueStrategy::LeastRecent, CallQueueStrategy::FewestCalls => $members->sortBy(fn (CallQueueMember $member): string => $this->priorityKey($member))->values(),
        };
    }

    private function priorityKey(CallQueueMember $member): string
    {
        return sprintf('%05d:%05d:%010d', $member->priority, $member->penalty, $member->id);
    }

    private function roundRobinKey(CallQueueMember $member): string
    {
        $lastCall = $member->last_call_at?->timestamp ?? 0;

        return sprintf('%010d:%05d:%05d:%010d', $lastCall, $member->priority, $member->penalty, $member->id);
    }

    private function randomKey(CallQueue $queue, CallQueueMember $member): string
    {
        return sprintf('%010u:%010d', crc32($queue->uuid.':'.$member->id), $member->id);
    }

    private function serializeMember(CallQueueMember $member): array
    {
        return [
            'id' => $member->id,
            'uuid' => $member->uuid,
            'member_type' => $member->member_type?->value ?? $member->member_type,
            'member_id' => $member->member_id,
            'extension_id' => $member->extension_id,
            'user_id' => $member->user_id,
            'priority' => $member->priority,
            'penalty' => $member->penalty,
            'is_active' => (bool) $member->is_active,
            'is_paused' => (bool) $member->is_paused,
            'paused_at' => $member->paused_at?->toISOString(),
            'pause_reason' => $member->pause_reason,
            'extension' => $member->extension ? [
                'id' => $member->extension->id,
                'number' => $member->extension->number,
                'label' => $member->extension->label,
                'status' => $member->extension->status?->value ?? $member->extension->status,
            ] : null,
            'user' => $member->user ? [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
            ] : null,
        ];
    }

    private function serializeOverflow(CallQueue $queue): ?array
    {
        if (! $queue->overflow_destination_type || ! $queue->overflow_destination_id) {
            return null;
        }

        return [
            'type' => $queue->overflow_destination_type,
            'id' => $queue->overflow_destination_id,
            'summary' => sprintf('%s:%s', $queue->overflow_destination_type, $queue->overflow_destination_id),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function notesForStrategy(CallQueueStrategy $strategy): array
    {
        return match ($strategy) {
            CallQueueStrategy::LeastRecent, CallQueueStrategy::FewestCalls => [
                'least_recent and fewest_calls are intentionally simplified in this slice and currently fall back to deterministic ordering.',
            ],
            default => [
                'Queue routing here is a configuration-time dry run only and does not place a live PBX call.',
            ],
        };
    }
}
