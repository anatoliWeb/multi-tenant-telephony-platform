<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\SwitchTenantRequest;
use App\Http\Resources\TenantContextResource;
use App\Http\Resources\TenantMembershipResource;
use App\Models\User;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class TenantController extends BaseController
{
    public function __construct(
        private readonly TenantBootstrapService $tenantBootstrapService,
        private readonly TenantContext $tenantContext
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $memberships = $user?->activeTenantMemberships()
            ->with('tenant')
            ->orderBy('created_at')
            ->get() ?? collect();

        return $this->successResponse([
            'tenants' => TenantMembershipResource::collection($memberships)->resolve(),
            'current_tenant_id' => $this->tenantContext->tenantId(),
        ], dt('notifications.success'));
    }

    public function show(Request $request)
    {
        $tenant = $this->tenantContext->requireTenant();
        $user = $request->user();

        $membership = $user instanceof User
            ? $this->tenantBootstrapService->activeMembershipFor($user, $tenant)
            : null;

        return $this->successResponse(
            TenantContextResource::make([
                'tenant' => $tenant,
                'membership' => $membership,
                'current_tenant_id' => $tenant->getKey(),
            ])->resolve(),
            dt('notifications.success')
        );
    }

    public function switchTenant(SwitchTenantRequest $request)
    {
        $identifier = (string) $request->validated('tenant_uuid');
        $tenant = $this->tenantBootstrapService->resolveTenantByIdentifier($identifier);

        if ($tenant === null) {
            abort(404, 'Tenant not found');
        }

        $user = $request->user();
        if ($user instanceof User && ! $this->tenantBootstrapService->userHasActiveMembership($user, $tenant)) {
            abort(403, 'Tenant access denied');
        }

        $this->tenantContext->setTenant($tenant);

        $membership = $user instanceof User
            ? $this->tenantBootstrapService->activeMembershipFor($user, $tenant)
            : null;

        return $this->successResponse(
            TenantContextResource::make([
                'tenant' => $tenant,
                'membership' => $membership,
                'current_tenant_id' => $tenant->getKey(),
            ])->resolve(),
            dt('notifications.success')
        );
    }
}
