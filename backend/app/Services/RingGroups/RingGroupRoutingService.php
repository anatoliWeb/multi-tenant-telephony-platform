<?php

namespace App\Services\RingGroups;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\RingGroups\RingGroupMemberType;
use App\Enums\RingGroups\RingGroupStrategy;
use App\Enums\TenantMembershipStatus;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RingGroupRoutingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(RingGroup $ringGroup): array
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $ringGroup->tenant_id !== (string) $tenant->getKey()) {
            abort(404, 'Ring group not found.');
        }

        $members = $ringGroup->members()
            ->with(['extension', 'user'])
            ->where('is_active', true)
            ->get()
            ->filter(fn (RingGroupMember $member): bool => $this->memberIsEligible($member))
            ->values();

        $orderedMembers = match ($ringGroup->strategy instanceof RingGroupStrategy ? $ringGroup->strategy : RingGroupStrategy::tryFrom((string) $ringGroup->strategy)) {
            RingGroupStrategy::Sequential => $members->sort(fn (RingGroupMember $left, RingGroupMember $right): int => [
                $left->priority,
                $left->id,
            ] <=> [
                $right->priority,
                $right->id,
            ])->values(),
            RingGroupStrategy::Random => $members->sortBy(fn (RingGroupMember $member): string => hash('sha256', $ringGroup->uuid.'|'.$member->uuid))->values(),
            default => $members->sort(fn (RingGroupMember $left, RingGroupMember $right): int => [
                $left->priority,
                $left->id,
            ] <=> [
                $right->priority,
                $right->id,
            ])->values(),
        };

        return [
            'ring_group' => [
                'id' => $ringGroup->id,
                'uuid' => $ringGroup->uuid,
                'name' => $ringGroup->name,
                'strategy' => $ringGroup->strategy?->value ?? $ringGroup->strategy,
                'status' => $ringGroup->status?->value ?? $ringGroup->status,
            ],
            'resolved_at' => Carbon::now()->toISOString(),
            'active_member_count' => $orderedMembers->count(),
            'members' => $orderedMembers->map(fn (RingGroupMember $member): array => $this->memberPayload($member))->all(),
            'failover' => [
                'type' => $ringGroup->failover_destination_type,
                'id' => $ringGroup->failover_destination_id,
            ],
        ];
    }

    private function memberIsEligible(RingGroupMember $member): bool
    {
        if (! $member->is_active) {
            return false;
        }

        $tenant = $this->tenantContext->requireTenant();

        if ($member->member_type instanceof RingGroupMemberType) {
            $memberType = $member->member_type;
        } else {
            $memberType = RingGroupMemberType::tryFrom((string) $member->member_type);
        }

        if ($memberType === RingGroupMemberType::Extension) {
            return (bool) $member->extension
                && (string) $member->extension->tenant_id === (string) $tenant->getKey()
                && ($member->extension->status instanceof ExtensionStatus
                    ? $member->extension->status === ExtensionStatus::Active
                    : (string) $member->extension->status === ExtensionStatus::Active->value);
        }

        if ($memberType === RingGroupMemberType::User) {
            if (! $member->user) {
                return false;
            }

            return $member->user->tenantMemberships()
                ->where('tenant_id', $tenant->getKey())
                ->where('status', TenantMembershipStatus::Active->value)
                ->exists();
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(RingGroupMember $member): array
    {
        return [
            'id' => $member->id,
            'uuid' => $member->uuid,
            'member_type' => $member->member_type?->value ?? $member->member_type,
            'priority' => $member->priority,
            'delay_seconds' => $member->delay_seconds,
            'timeout_seconds' => $member->timeout_seconds,
            'is_active' => $member->is_active,
            'extension' => $member->extension ? [
                'id' => $member->extension->id,
                'number' => $member->extension->number,
                'label' => $member->extension->label,
            ] : null,
            'user' => $member->user ? [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
            ] : null,
        ];
    }
}
