<?php

namespace App\Services;

use App\Actions\Notifications\CreateNotificationAction;
use App\DTO\NotificationPayloadDTO;
use App\Events\Notifications\NotificationCreated;
use App\Jobs\Notifications\CreateNotificationJob;
use App\Jobs\Realtime\BroadcastDatabaseNotificationCreatedJob;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function __construct(
        protected CreateNotificationAction $createNotificationAction,
        protected NotificationPreferenceService $notificationPreferenceService,
    ) {
    }

    /**
     * Get notifications for authenticated user.
     *
     * WHY:
     * Notification query logic belongs to service layer,
     * not to HTTP controllers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(User $user, array $filters = []): array
    {
        $query = $user->notifications()->latest();

        if (($filters['status'] ?? null) === 'unread') {
            $query->whereNull('read_at');
        }

        if (($filters['status'] ?? null) === 'read') {
            $query->whereNotNull('read_at');
        }

        return $query
            ->limit((int) ($filters['limit'] ?? 50))
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->transform($notification))
            ->values()
            ->all();
    }

    /**
     * Get unread notifications count.
     *
     * WHY:
     * Frontend notification badges need a lightweight endpoint.
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark one notification as read.
     */
    public function markAsRead(User $user, string $notificationId): ?array
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return null;
        }

        $notification->markAsRead();

        return $this->transform($notification->fresh());
    }

    /**
     * Mark all user notifications as read.
     */
    public function markAllAsRead(User $user): int
    {
        $notifications = $user->unreadNotifications()->get();

        $notifications->markAsRead();

        return $notifications->count();
    }

    /**
     * Delete one notification.
     */
    public function delete(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return false;
        }

        return (bool) $notification->delete();
    }

    /**
     * Create database notification for user.
     *
     * WHY:
     * This is a foundation method for future system notifications.
     * Broadcast/realtime delivery can be added later without changing controllers.
     */
    public function createForUser(User $user, string $title, string $message, array $data = []): array
    {
        if (!$this->notificationPreferenceService->isEnabled($user, 'system.enabled')) {
            return [];
        }

        $notification = $this->createNotificationAction->execute($user, $title, $message, $data);
        $this->dispatchNotificationCreatedEvent($notification, $user->id);
        $this->dispatchNotificationBroadcastBridge($user, $notification);

        return $this->transform($notification);
    }

    /**
     * Dispatch async notification create workflow.
     *
     * WHY:
     * Keeps current synchronous API behavior intact while enabling queued
     * delivery for background use-cases.
     *
     * @param array<string, mixed> $data
     */
    public function dispatchForUser(User $user, string $title, string $message, array $data = []): void
    {
        if (!$this->notificationPreferenceService->isEnabled($user, 'system.enabled')) {
            return;
        }

        CreateNotificationJob::dispatch(
            userId: $user->id,
            title: $title,
            message: $message,
            data: $data,
        );
    }

    /**
     * Transform notification to stable API shape.
     *
     * WHY:
     * Frontend should not depend on raw Laravel notification structure.
     *
     * @return array<string, mixed>
     */
    protected function transform(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];

        return (new NotificationPayloadDTO(
            id: $notification->id,
            type: $notification->type,
            title: $data['title'] ?? null,
            message: $data['message'] ?? null,
            data: $data,
            isRead: $notification->read_at !== null,
            readAt: $notification->read_at?->toISOString(),
            createdAt: $notification->created_at?->toISOString(),
        ))->toArray();
    }

    protected function dispatchNotificationCreatedEvent(DatabaseNotification $notification, ?int $actorId = null): void
    {
        $payload = $notification->data ?? [];

        event(new NotificationCreated(
            notificationId: $notification->id,
            notifiableId: (int) $notification->notifiable_id,
            type: (string) $notification->type,
            title: data_get($payload, 'title'),
            message: data_get($payload, 'message'),
            actorId: $actorId,
            occurredAt: now()->toIso8601String(),
        ));
    }

    protected function dispatchNotificationBroadcastBridge(User $user, DatabaseNotification $notification): void
    {
        if (!$this->notificationPreferenceService->isEnabled($user, 'realtime.enabled')) {
            return;
        }

        BroadcastDatabaseNotificationCreatedJob::dispatch(
            userId: $user->id,
            payload: [
                'id' => $notification->id,
                'type' => (string) $notification->type,
                'title' => data_get($notification->data, 'title'),
                'message' => data_get($notification->data, 'message'),
                'is_read' => $notification->read_at !== null,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ],
        );
    }
}
