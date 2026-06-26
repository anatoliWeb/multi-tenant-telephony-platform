<?php

namespace App\Services\PhoneNumbers;

use App\Enums\TenantMembershipStatus;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class UserPrimaryDidResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function resolve(User $user, Tenant|string|null $tenant = null): ?PhoneNumber
    {
        $tenantId = $tenant instanceof Tenant
            ? (string) $tenant->getKey()
            : (is_string($tenant) && $tenant !== '' ? $tenant : (string) $this->tenantContext->requireTenant()->getKey());

        $hasActiveMembership = $user->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantMembershipStatus::Active->value)
            ->exists();

        if (! $hasActiveMembership) {
            return null;
        }

        return PhoneNumber::query()
            ->forTenant($tenantId)
            ->where('assigned_user_id', $user->getKey())
            ->where('is_primary', true)
            ->first();
    }
}
