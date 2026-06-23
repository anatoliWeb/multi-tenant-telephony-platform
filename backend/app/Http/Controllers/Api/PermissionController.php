<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StorePermissionRequest;
use App\Http\Requests\Api\UpdatePermissionRequest;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

/**
 * Permissions API Controller.
 *
 * WHY:
 * - Handles HTTP request/response layer only
 * - Delegates permission business logic to PermissionService
 * - Keeps API response contract stable for Vue Admin and Angular Dashboard
 */
class PermissionController extends BaseController
{
    /**
     * Inject PermissionService.
     *
     * WHY:
     * PermissionService owns permission domain logic:
     * - permission queries
     * - permission creation/update
     * - translation persistence
     * - type/group inference
     * - API payload transformation
     */
    public function __construct(
        protected PermissionService $permissionService
    ) {
    }

    /**
     * Get list of permissions.
     *
     * WHY:
     * Used by frontend permission management screens.
     * Query and transformation logic stays inside PermissionService.
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->permissionService->getPermissionsForApi(),
            dt('notifications.success')
        );
    }

    /**
     * Create new permission.
     *
     * WHY:
     * The controller validates request data and delegates domain work
     * to PermissionService:
     * - create permission
     * - persist translations
     * - load roles usage state
     *
     * Response shape is intentionally kept unchanged for frontend compatibility.
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->create($request->validated());

        return $this->successResponse(
            $this->permissionService->transformPermission($permission),
            dt('notifications.created'),
            201
        );
    }

    /**
     * Update existing permission.
     *
     * WHY:
     * PermissionService handles update transaction and translation sync.
     * The technical permission name remains immutable for RBAC safety.
     *
     * Response shape is intentionally kept unchanged for frontend compatibility.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $updated = $this->permissionService->update($permission, $request->validated());

        return $this->successResponse(
            $this->permissionService->transformPermission($updated),
            dt('notifications.updated')
        );
    }
}
