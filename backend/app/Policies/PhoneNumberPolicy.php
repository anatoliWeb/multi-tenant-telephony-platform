<?php

namespace App\Policies;

use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class PhoneNumberPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('phone_numbers.view');
    }

    public function view(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->viewAny($user) && (string) $phoneNumber->tenant_id === (string) $this->tenantContext->tenantId();
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('phone_numbers.create');
    }

    public function update(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->view($user, $phoneNumber) && $user->hasPermission('phone_numbers.update');
    }

    public function delete(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->view($user, $phoneNumber) && $user->hasPermission('phone_numbers.delete');
    }

    public function assign(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->view($user, $phoneNumber) && $user->hasPermission('phone_numbers.assign');
    }

    public function setPrimary(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->view($user, $phoneNumber) && $user->hasPermission('phone_numbers.set_primary');
    }

    public function provision(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->view($user, $phoneNumber) && $user->hasPermission('phone_numbers.provision');
    }

    public function release(User $user, PhoneNumber $phoneNumber): bool
    {
        return $this->view($user, $phoneNumber) && $user->hasPermission('phone_numbers.release');
    }
}
