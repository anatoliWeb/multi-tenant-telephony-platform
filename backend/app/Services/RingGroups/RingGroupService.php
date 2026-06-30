<?php

namespace App\Services\RingGroups;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\RingGroups\RingGroupMemberType;
use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\RingGroups\RingGroupStrategy;
use App\Enums\TenantMembershipStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\Extension;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\RingGroups\RingGroupCreated;
use App\Events\RingGroups\RingGroupDeleted;
use App\Events\RingGroups\RingGroupMemberChanged;
use App\Events\RingGroups\RingGroupUpdated;

class RingGroupService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RingGroupRoutingService $routingService,
    ) {
    }

    public function create(array $payload, User $actor): RingGroup
    {
        return DB::transaction(function () use ($payload, $actor): RingGroup {
            $tenantId = $this->requireTenantId();
            $name = trim((string) $payload['name']);
            $slug = $this->normalizeSlug($payload['slug'] ?? null, $name);
            $this->assertUniqueSlug($tenantId, $slug);
            $this->assertFailoverDoesNotLoop($tenantId, $payload);

            $ringGroup = RingGroup::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? null,
                'strategy' => $payload['strategy'] ?? RingGroupStrategy::Simultaneous->value,
                'status' => $payload['status'] ?? RingGroupStatus::Active->value,
                'ring_timeout_seconds' => (int) ($payload['ring_timeout_seconds'] ?? 20),
                'max_ring_duration_seconds' => (int) ($payload['max_ring_duration_seconds'] ?? 120),
                'failover_destination_type' => $payload['failover_destination_type'] ?? null,
                'failover_destination_id' => $payload['failover_destination_id'] ?? null,
                'settings' => $payload['settings'] ?? [],
                'metadata' => $payload['metadata'] ?? [],
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            event(new RingGroupCreated($ringGroup));

            return $ringGroup;
        });
    }

    public function update(RingGroup $ringGroup, array $payload, User $actor): RingGroup
    {
        return DB::transaction(function () use ($ringGroup, $payload, $actor): RingGroup {
            $tenantId = (string) $ringGroup->tenant_id;
            $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : $ringGroup->name;
            $slug = $this->normalizeSlug($payload['slug'] ?? $ringGroup->slug, $name);
            $this->assertUniqueSlug($tenantId, $slug, $ringGroup);
            $this->assertFailoverDoesNotLoop($tenantId, $payload, $ringGroup);

            $ringGroup->update([
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? $ringGroup->description,
                'strategy' => $payload['strategy'] ?? ($ringGroup->strategy?->value ?? $ringGroup->strategy),
                'status' => $payload['status'] ?? ($ringGroup->status?->value ?? $ringGroup->status),
                'ring_timeout_seconds' => $payload['ring_timeout_seconds'] ?? $ringGroup->ring_timeout_seconds,
                'max_ring_duration_seconds' => $payload['max_ring_duration_seconds'] ?? $ringGroup->max_ring_duration_seconds,
                'failover_destination_type' => array_key_exists('failover_destination_type', $payload) ? $payload['failover_destination_type'] : $ringGroup->failover_destination_type,
                'failover_destination_id' => array_key_exists('failover_destination_id', $payload) ? $payload['failover_destination_id'] : $ringGroup->failover_destination_id,
                'settings' => $payload['settings'] ?? $ringGroup->settings ?? [],
                'metadata' => $payload['metadata'] ?? $ringGroup->metadata ?? [],
                'updated_by' => $actor->getKey(),
            ]);

            event(new RingGroupUpdated($ringGroup->fresh()));

            return $ringGroup->fresh(['members.extension', 'members.user']);
        });
    }

    public function delete(RingGroup $ringGroup): void
    {
        DB::transaction(function () use ($ringGroup): void {
            event(new RingGroupDeleted($ringGroup));
            $ringGroup->delete();
        });
    }

    public function createMember(RingGroup $ringGroup, array $payload): RingGroupMember
    {
        return DB::transaction(function () use ($ringGroup, $payload): RingGroupMember {
            $this->assertGroupTenant($ringGroup);
            $normalized = $this->normalizeMemberPayload($payload);
            $this->assertMemberTargetEligible($normalized['member_type'], $normalized['extension_id'], $normalized['user_id']);
            $this->assertMemberUniqueness($ringGroup, $normalized['member_type'], $normalized['extension_id'], $normalized['user_id']);

            $member = RingGroupMember::query()->create(array_merge($normalized, [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $ringGroup->tenant_id,
                'ring_group_id' => $ringGroup->getKey(),
            ]));

            event(new RingGroupMemberChanged($member));

            return $member->fresh(['extension', 'user']);
        });
    }

    public function updateMember(RingGroup $ringGroup, RingGroupMember $member, array $payload): RingGroupMember
    {
        return DB::transaction(function () use ($ringGroup, $member, $payload): RingGroupMember {
            $this->assertGroupTenant($ringGroup);
            $this->assertMemberTenant($ringGroup, $member);
            $normalized = $this->normalizeMemberPayload($payload, $member);
            $this->assertMemberTargetEligible($normalized['member_type'], $normalized['extension_id'], $normalized['user_id']);
            $this->assertMemberUniqueness($ringGroup, $normalized['member_type'], $normalized['extension_id'], $normalized['user_id'], $member);

            $member->update($normalized);

            event(new RingGroupMemberChanged($member->fresh()));

            return $member->fresh(['extension', 'user']);
        });
    }

    public function deleteMember(RingGroup $ringGroup, RingGroupMember $member): void
    {
        DB::transaction(function () use ($ringGroup, $member): void {
            $this->assertGroupTenant($ringGroup);
            $this->assertMemberTenant($ringGroup, $member);
            event(new RingGroupMemberChanged($member));
            $member->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function testRoute(RingGroup $ringGroup): array
    {
        return $this->routingService->resolve($ringGroup->fresh(['members.extension', 'members.user']));
    }

    private function requireTenantId(): string
    {
        return (string) $this->tenantContext->requireTenant()->getKey();
    }

    private function assertGroupTenant(RingGroup $ringGroup): void
    {
        if ((string) $ringGroup->tenant_id !== (string) $this->tenantContext->requireTenant()->getKey()) {
            abort(404, 'Ring group not found.');
        }
    }

    private function assertMemberTenant(RingGroup $ringGroup, RingGroupMember $member): void
    {
        if ((string) $member->tenant_id !== (string) $ringGroup->tenant_id || (string) $member->ring_group_id !== (string) $ringGroup->getKey()) {
            abort(404, 'Ring group member not found.');
        }
    }

    private function normalizeSlug(mixed $slug, string $name): string
    {
        $candidate = trim((string) $slug);

        return $candidate !== '' ? Str::slug($candidate) : Str::slug($name);
    }

    private function assertUniqueSlug(string $tenantId, string $slug, ?RingGroup $ringGroup = null): void
    {
        $query = RingGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);

        if ($ringGroup instanceof RingGroup) {
            $query->where('id', '!=', $ringGroup->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('A ring group with this slug already exists in the active tenant.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertFailoverDoesNotLoop(string $tenantId, array $payload, ?RingGroup $ringGroup = null): void
    {
        $type = array_key_exists('failover_destination_type', $payload)
            ? $payload['failover_destination_type']
            : $ringGroup?->failover_destination_type;
        $destinationId = array_key_exists('failover_destination_id', $payload)
            ? $payload['failover_destination_id']
            : $ringGroup?->failover_destination_id;

        if (! in_array($type, ['extension', 'user'], true) || ! $destinationId) {
            return;
        }

        $ringGroupId = $ringGroup?->getKey();
        $isLoop = RingGroupMember::query()
            ->where('tenant_id', $tenantId)
            ->where('ring_group_id', $ringGroupId)
            ->where('is_active', true)
            ->where(function ($query) use ($type, $destinationId): void {
                if ($type === 'extension') {
                    $query->where('extension_id', $destinationId);
                    return;
                }

                $query->where('user_id', $destinationId);
            })
            ->exists();

        if ($isLoop) {
            throw new TelephonyConflictException('Failover destination cannot point back to an active member in the same ring group.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMemberPayload(array $payload, ?RingGroupMember $existing = null): array
    {
        $memberType = (string) ($payload['member_type'] ?? $existing?->member_type?->value ?? $existing?->member_type);
        $priority = (int) ($payload['priority'] ?? $existing?->priority ?? 1);
        $delaySeconds = (int) ($payload['delay_seconds'] ?? $existing?->delay_seconds ?? 0);
        $timeoutSeconds = (int) ($payload['timeout_seconds'] ?? $existing?->timeout_seconds ?? 20);
        $isActive = array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : (bool) ($existing?->is_active ?? true);

        return [
            'member_type' => $memberType,
            'extension_id' => $memberType === RingGroupMemberType::Extension->value ? (int) ($payload['extension_id'] ?? $existing?->extension_id) : null,
            'user_id' => $memberType === RingGroupMemberType::User->value ? (int) ($payload['user_id'] ?? $existing?->user_id) : null,
            'priority' => $priority,
            'delay_seconds' => $delaySeconds,
            'timeout_seconds' => $timeoutSeconds,
            'is_active' => $isActive,
            'metadata' => $payload['metadata'] ?? $existing?->metadata ?? [],
        ];
    }

    private function assertMemberTargetEligible(string $memberType, ?int $extensionId, ?int $userId): void
    {
        $tenantId = $this->requireTenantId();

        if ($memberType === RingGroupMemberType::Extension->value) {
            $exists = Extension::query()
                ->forTenant($tenantId)
                ->whereKey($extensionId)
                ->where('status', ExtensionStatus::Active->value)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Ring group member extension must belong to the active tenant and be active.');
            }
        }

        if ($memberType === RingGroupMemberType::User->value) {
            $exists = User::query()
                ->whereKey($userId)
                ->whereHas('tenantMemberships', fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', TenantMembershipStatus::Active->value))
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Ring group member user must belong to the active tenant and have an active membership.');
            }
        }
    }

    private function assertMemberUniqueness(RingGroup $ringGroup, string $memberType, ?int $extensionId, ?int $userId, ?RingGroupMember $ignore = null): void
    {
        $query = RingGroupMember::query()
            ->where('tenant_id', $ringGroup->tenant_id)
            ->where('ring_group_id', $ringGroup->getKey())
            ->where('member_type', $memberType)
            ->where('is_active', true);

        if ($memberType === RingGroupMemberType::Extension->value) {
            $query->where('extension_id', $extensionId);
        } else {
            $query->where('user_id', $userId);
        }

        if ($ignore instanceof RingGroupMember) {
            $query->where('id', '!=', $ignore->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('This active member already exists in the ring group.');
        }
    }
}
