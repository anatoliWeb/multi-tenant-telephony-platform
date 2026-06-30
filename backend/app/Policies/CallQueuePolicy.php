<?php

namespace App\Policies;

use App\Enums\TenantStatus;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class CallQueuePolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantIsActive() && $user->hasPermission('call_queues.view');
    }

    public function view(User $user, CallQueue $queue): bool
    {
        return $this->viewAny($user) && (string) $queue->tenant_id === (string) $this->tenantContext->tenantId();
    }

    public function create(User $user): bool
    {
        return $this->tenantIsActive() && $user->hasPermission('call_queues.create');
    }

    public function update(User $user, CallQueue $queue): bool
    {
        return $this->view($user, $queue) && $user->hasPermission('call_queues.update');
    }

    public function delete(User $user, CallQueue $queue): bool
    {
        return $this->view($user, $queue) && $user->hasPermission('call_queues.delete');
    }

    public function manageMembers(User $user, CallQueue $queue): bool
    {
        return $this->view($user, $queue) && $user->hasPermission('call_queues.manage_members');
    }

    public function pauseMembers(User $user, CallQueue $queue, CallQueueMember $member): bool
    {
        if (! $this->view($user, $queue)) {
            return false;
        }

        if ($this->manageMembers($user, $queue)) {
            return true;
        }

        // Self-management is intentionally narrower than admin management so
        // agents can only pause or resume their own queue membership.
        return $user->hasPermission('call_queues.pause_members')
            && $member->member_type?->value === 'user'
            && (int) $member->user_id === (int) $user->getKey();
    }

    public function testRoute(User $user, CallQueue $queue): bool
    {
        return $this->view($user, $queue) && $user->hasPermission('call_queues.test_route');
    }

    private function tenantIsActive(): bool
    {
        $tenant = $this->tenantContext->tenant();

        return $tenant instanceof \App\Models\Tenant
            && ($tenant->status?->value ?? $tenant->status) === TenantStatus::Active->value;
    }
}
