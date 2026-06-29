<?php

namespace App\Policies;

use App\Models\CallLog;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

class CallLogPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->hasTenant()
            && $user->hasPermission('call_logs.view')
            && $user->hasAnyPermission(['call_logs.view_own', 'call_logs.view_all']);
    }

    public function view(User $user, CallLog $callLog): bool
    {
        if (! $this->viewAny($user) || (string) $callLog->tenant_id !== (string) $this->tenantContext->tenantId()) {
            return false;
        }

        if ($user->hasPermission('call_logs.view_all')) {
            return true;
        }

        return (int) $callLog->caller_user_id === (int) $user->getKey()
            || (int) $callLog->callee_user_id === (int) $user->getKey();
    }

    public function viewStatistics(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('call_logs.view_statistics');
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('call_logs.export');
    }
}
