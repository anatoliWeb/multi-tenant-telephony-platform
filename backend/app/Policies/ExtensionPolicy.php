<?php

namespace App\Policies;

use App\Models\Extension;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class ExtensionPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('extensions.view');
    }

    public function view(User $user, Extension $extension): bool
    {
        return $this->viewAny($user) && (string) $extension->tenant_id === (string) $this->tenantContext->tenantId();
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('extensions.create');
    }

    public function update(User $user, Extension $extension): bool
    {
        return $this->view($user, $extension) && $user->hasPermission('extensions.update');
    }

    public function delete(User $user, Extension $extension): bool
    {
        return $this->view($user, $extension) && $user->hasPermission('extensions.delete');
    }

    public function manageCredentials(User $user, Extension $extension): bool
    {
        return $this->view($user, $extension) && $user->hasPermission('extensions.manage_credentials');
    }
}
