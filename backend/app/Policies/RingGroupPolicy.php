<?php

namespace App\Policies;

use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class RingGroupPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('ring_groups.view');
    }

    public function view(User $user, RingGroup $ringGroup): bool
    {
        return $this->viewAny($user) && (string) $ringGroup->tenant_id === (string) $this->tenantContext->tenantId();
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('ring_groups.create');
    }

    public function update(User $user, RingGroup $ringGroup): bool
    {
        return $this->view($user, $ringGroup) && $user->hasPermission('ring_groups.update');
    }

    public function delete(User $user, RingGroup $ringGroup): bool
    {
        return $this->view($user, $ringGroup) && $user->hasPermission('ring_groups.delete');
    }

    public function manageMembers(User $user, RingGroup $ringGroup): bool
    {
        return $this->view($user, $ringGroup) && $user->hasPermission('ring_groups.manage_members');
    }

    public function testRoute(User $user, RingGroup $ringGroup): bool
    {
        return $this->view($user, $ringGroup) && $user->hasPermission('ring_groups.test_route');
    }
}
