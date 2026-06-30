<?php

namespace App\Policies;

use App\Models\IvrMenu;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class IvrMenuPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantIsActive() && $user->hasPermission('ivr.view');
    }

    public function view(User $user, IvrMenu $ivrMenu): bool
    {
        return $this->viewAny($user) && $this->belongsToCurrentTenant($ivrMenu);
    }

    public function create(User $user): bool
    {
        return $this->tenantIsActive() && $user->hasPermission('ivr.create');
    }

    public function update(User $user, IvrMenu $ivrMenu): bool
    {
        return $this->view($user, $ivrMenu) && $user->hasPermission('ivr.update');
    }

    public function delete(User $user, IvrMenu $ivrMenu): bool
    {
        return $this->view($user, $ivrMenu) && $user->hasPermission('ivr.delete');
    }

    public function manageOptions(User $user, IvrMenu $ivrMenu): bool
    {
        return $this->view($user, $ivrMenu) && $user->hasPermission('ivr.manage_options');
    }

    public function testRoute(User $user, IvrMenu $ivrMenu): bool
    {
        return $this->view($user, $ivrMenu) && $user->hasPermission('ivr.test_route');
    }

    private function tenantIsActive(): bool
    {
        return $this->tenantContext->hasTenant();
    }

    private function belongsToCurrentTenant(IvrMenu $ivrMenu): bool
    {
        return $ivrMenu->isInCurrentTenant();
    }
}
