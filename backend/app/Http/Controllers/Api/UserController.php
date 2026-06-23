<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Users API Controller.
 *
 * WHY:
 * - Handles HTTP layer only
 * - Delegates business logic to UserService
 * - Keeps responses consistent for frontend (SaaS API style)
 */
class UserController extends BaseController
{
    /**
     * Inject UserService (business logic layer)
     */
    public function __construct(
        protected UserService $userService
    ) {
        /**
         * IMPORTANT:
         * In modern Laravel structure we prefer to define
         * permissions in routes/api.php instead of controller.
         *
         * This avoids coupling and makes routes more explicit.
         */
    }

    /**
     * Get list of users.
     *
     * WHY:
     * Used by DataTable on frontend.
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->getUsersForDataTable($request->query());

        return $this->successResponse(
            UserResource::collection(collect($users))->resolve(),
            dt('notifications.success')
        );
    }

    /**
     * Get single user by ID.
     *
     * WHY:
     * Used for "Edit user" modal (prefill form)
     */
    public function show(int $user): JsonResponse
    {
        $record = $this->userService->getById($user);

        return $this->successResponse(
            (new UserResource($record))->resolve(),
            dt('notifications.success')
        );
    }

    /**
     * Create new user.
     *
     * WHY:
     * Called from "Create User" modal.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $record = $this->userService->create($request->validated());

        return $this->successResponse(
            (new UserResource($record))->resolve(),
            dt('notifications.created'),
            201
        );
    }

    /**
     * Update existing user.
     *
     * WHY:
     * Called from "Edit User" modal.
     */
    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        $record = $this->userService->update($user, $request->validated());

        return $this->successResponse(
            (new UserResource($record))->resolve(),
            dt('notifications.updated')
        );
    }

    /**
     * Delete user.
     *
     * WHY:
     * Called from table "Delete" action.
     */
    public function destroy(int $user): JsonResponse
    {
        $this->userService->delete($user);

        return $this->successResponse([
            'deleted' => true,
        ], dt('notifications.deleted'));
    }
}
