<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Requests\Api\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;

/**
 * Roles API Controller.
 *
 * WHY:
 * - Handles HTTP request/response layer only
 * - Delegates role business logic to RoleService
 * - Keeps API response contract stable for Vue Admin and Angular Dashboard
 */
class RoleController extends BaseController
{
    /**
     * Inject RoleService.
     *
     * WHY:
     * RoleService owns role domain logic:
     * - role queries
     * - role creation/update
     * - permissions synchronization
     * - translation persistence
     * - API metadata preparation
     */
    public function __construct(
        protected RoleService $roleService
    ) {
    }

    /**
     * Get list of roles.
     *
     * WHY:
     * Used by frontend role management screens.
     * Query logic stays inside RoleService so controller remains thin.
     */
    public function index(): JsonResponse
    {
        $roles = $this->roleService->getRolesForApi();

        return $this->successResponse(
            RoleResource::collection($roles)->resolve(),
            dt('notifications.success')
        );
    }

    /**
     * Create new role.
     *
     * WHY:
     * The controller validates the request and delegates all domain work
     * to RoleService:
     * - create role
     * - sync permissions
     * - persist translations
     * - load counters
     *
     * Response shape is intentionally kept unchanged for frontend compatibility.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return $this->successResponse(
            array_merge(
                (new RoleResource($role))->resolve(),
                $this->roleService->buildApiMeta($role)
            ),
            dt('notifications.created'),
            201
        );
    }

    /**
     * Update existing role.
     *
     * WHY:
     * RoleService handles update transaction and related sync logic.
     * The technical role name remains immutable inside the service
     * to keep RBAC contracts stable.
     *
     * Response shape is intentionally kept unchanged for frontend compatibility.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $updated = $this->roleService->update($role, $request->validated());

        return $this->successResponse(
            array_merge(
                (new RoleResource($updated))->resolve(),
                $this->roleService->buildApiMeta($updated)
            ),
            dt('notifications.updated')
        );
    }
}
