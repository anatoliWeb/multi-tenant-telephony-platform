<?php

namespace App\Policies;

use App\Models\ContactTag;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class ContactTagPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('contacts.view');
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->hasTenant() && $user->hasPermission('contacts.manage_tags');
    }

    public function update(User $user, ContactTag $tag): bool
    {
        return $this->create($user) && (string) $tag->tenant_id === (string) $this->tenantContext->tenantId();
    }

    public function delete(User $user, ContactTag $tag): bool
    {
        return $this->update($user, $tag);
    }
}
