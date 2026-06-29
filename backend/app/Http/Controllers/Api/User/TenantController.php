<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\SwitchTenantRequest;
use App\Http\Resources\TenantContextResource;
use App\Http\Resources\TenantMembershipResource;
use App\Http\Resources\TenantSummaryResource;
use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class TenantController extends BaseController
{
    public function __construct(
        private readonly TenantBootstrapService $tenantBootstrapService,
        private readonly TenantContext $tenantContext,
        private readonly PermissionCacheService $permissionCacheService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $tenants = $user instanceof User
            ? $this->tenantBootstrapService->accessibleTenantsForUser($user)
            : collect();

        $tenantPayload = $user instanceof User && $this->tenantBootstrapService->isPlatformAdmin($user)
            ? TenantSummaryResource::collection($tenants)->resolve()
            : TenantMembershipResource::collection($tenants)->resolve();

        return $this->successResponse([
            'tenants' => $tenantPayload,
            'current_tenant_id' => $this->tenantContext->tenantId(),
            'platform_permissions' => $user instanceof User ? $this->permissionCacheService->getPlatformPermissionsForUser($user) : [],
            'tenant_permissions' => $user instanceof User && $this->tenantContext->hasTenant()
                ? $this->permissionCacheService->getTenantPermissionsForUser($user, $this->tenantContext->requireTenant())
                : [],
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
                'platform_permissions' => $user instanceof User ? $this->permissionCacheService->getPlatformPermissionsForUser($user) : [],
                'tenant_permissions' => $user instanceof User ? $this->permissionCacheService->getTenantPermissionsForUser($user, $tenant) : [],
                'permissions' => $user instanceof User ? $this->permissionCacheService->getTenantPermissionsForUser($user, $tenant) : [],
            ])->resolve(),
            dt('notifications.success')
        );
    }

    public function switchTenant(SwitchTenantRequest $request)
    {
        $identifier = (string) $request->validated('tenant_uuid');
        $tenant = $this->tenantBootstrapService->resolveTenantByIdentifier($identifier);

        if ($tenant === null || ! $this->tenantBootstrapService->tenantIsActive($tenant)) {
            abort(403, 'Tenant access denied');
        }

        $user = $request->user();
        if ($user instanceof User && ! $this->tenantBootstrapService->canAccessTenant($user, $tenant)) {
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
                'platform_permissions' => $user instanceof User ? $this->permissionCacheService->getPlatformPermissionsForUser($user) : [],
                'tenant_permissions' => $user instanceof User ? $this->permissionCacheService->getTenantPermissionsForUser($user, $tenant) : [],
                'permissions' => $user instanceof User ? $this->permissionCacheService->getTenantPermissionsForUser($user, $tenant) : [],
            ])->resolve(),
            dt('notifications.success')
        );
    }
}
