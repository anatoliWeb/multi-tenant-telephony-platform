<?php

namespace App\Services\Tenancy;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TenantBootstrapService
{
    public const DEFAULT_TENANT_UUID = '11111111-1111-1111-1111-111111111111';
    public const SECONDARY_TENANT_UUID = '22222222-2222-2222-2222-222222222222';
    public const SUSPENDED_TENANT_UUID = '33333333-3333-3333-3333-333333333333';

    public function resolveTenantByIdentifier(string $identifier): ?Tenant
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return Tenant::query()
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    public function accessibleTenantsForUser(User $user): Collection
    {
        if ($this->isPlatformAdmin($user)) {
            return Tenant::query()
                ->where('status', TenantStatus::Active->value)
                ->orderBy('name')
                ->get();
        }

        return TenantMembership::query()
            ->where('user_id', $user->getKey())
            ->where('status', TenantMembershipStatus::Active->value)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active->value))
            ->with(['tenant'])
            ->orderBy('created_at')
            ->get();
    }

    public function userHasActiveMembership(User $user, Tenant $tenant): bool
    {
        if (! $this->tenantIsActive($tenant)) {
            return false;
        }

        return TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', TenantMembershipStatus::Active->value)
            ->exists();
    }

    public function canAccessTenant(User $user, Tenant $tenant): bool
    {
        if (! $this->tenantIsActive($tenant)) {
            return false;
        }

        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        return $this->userHasActiveMembership($user, $tenant);
    }

    public function activeMembershipFor(User $user, Tenant $tenant): ?TenantMembership
    {
        if (! $this->userHasActiveMembership($user, $tenant)) {
            return null;
        }

        return TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', TenantMembershipStatus::Active->value)
            ->with(['tenant', 'user', 'invitedBy'])
            ->first();
    }

    public function isPlatformAdmin(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function tenantIsActive(Tenant $tenant): bool
    {
        $status = $tenant->status instanceof TenantStatus ? $tenant->status : TenantStatus::tryFrom((string) $tenant->status);

        return $status === TenantStatus::Active;
    }
}
