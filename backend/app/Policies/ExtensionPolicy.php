<?php

namespace App\Policies;

use App\Enums\Extensions\ExtensionStatus;
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

    public function viewSipProfile(User $user, Extension $extension): bool
    {
        return $this->tenantContext->hasTenant()
            && $user->hasPermission('call_control.view')
            && $this->belongsToCurrentTenant($extension)
            && $this->isCallable($extension);
    }

    private function belongsToCurrentTenant(Extension $extension): bool
    {
        return (string) $extension->tenant_id === (string) $this->tenantContext->tenantId();
    }

    private function isCallable(Extension $extension): bool
    {
        return $extension->status === ExtensionStatus::Active;
    }
}
