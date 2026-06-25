<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class ContactPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->viewAny($user) && (string) $contact->tenant_id === (string) $this->tenantContext->tenantId();
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact) && $user->hasPermission('contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact) && $user->hasPermission('contacts.delete');
    }

    public function import(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('contacts.import');
    }

    public function export(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('contacts.export');
    }
}
