<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Notifications API Controller.
 *
 * WHY:
 * - Handles HTTP request/response layer only
 * - Delegates notification business logic to NotificationService
 * - Keeps notification API contract stable for Vue Admin and Angular Dashboard
 */
class NotificationController extends BaseController
{
    public function __construct(
        protected NotificationService $notificationService,
        protected NotificationPreferenceService $notificationPreferenceService,
    ) {
    }

    /**
     * Get authenticated user notifications.
     *
     * Query params:
     * - status: all|read|unread
     * - limit: number
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $this->notificationService->getForUser($user, $request->query()),
            dt('notifications.success')
        );
    }

    /**
     * Get unread notifications count.
     *
     * WHY:
     * Used by notification badges in frontend layouts.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse([
            'count' => $this->notificationService->unreadCount($user),
        ], dt('notifications.success'));
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $record = $this->notificationService->markAsRead($user, $notification);

        if (!$record) {
            return $this->errorResponse(
                dt('notifications.not_found'),
                null,
                404
            );
        }

        return $this->successResponse(
            $record,
            dt('notifications.updated')
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse([
            'updated' => $this->notificationService->markAllAsRead($user),
        ], dt('notifications.updated'));
    }

    /**
     * Delete notification.
     */
    public function destroy(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleted = $this->notificationService->delete($user, $notification);

        if (!$deleted) {
            return $this->errorResponse(
                dt('notifications.not_found'),
                null,
                404
            );
        }

        return $this->successResponse([
            'deleted' => true,
        ], dt('notifications.deleted'));
    }

    public function preferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse([
            'preferences' => $this->notificationPreferenceService->getForUser($user),
        ], dt('notifications.success'));
    }

    /**
     * @throws ValidationException
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $preferences = $validated['preferences'] ?? [];
        $allowed = array_keys($this->notificationPreferenceService->defaults());
        $unknown = array_diff(array_keys($preferences), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'preferences' => ['Unknown preference keys: '.implode(', ', $unknown)],
            ]);
        }

        return $this->successResponse([
            'preferences' => $this->notificationPreferenceService->updateForUser($user, $preferences),
        ], dt('notifications.updated'));
    }
}
