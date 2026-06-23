<?php

namespace App\Jobs\Notifications;

use App\Actions\Notifications\CreateNotificationAction;
use App\Events\Notifications\NotificationCreated;
use App\Jobs\Realtime\BroadcastDatabaseNotificationCreatedJob;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Must remain <= worker timeout.
     */
    public int $timeout = 60;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public array $data = [],
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(
        CreateNotificationAction $createNotificationAction,
        ?NotificationPreferenceService $notificationPreferenceService = null,
    ): void
    {
        $notificationPreferenceService ??= app(NotificationPreferenceService::class);

        $user = User::query()->find($this->userId);

        if (!$user) {
            Log::warning('CreateNotificationJob skipped: user not found', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        if (!$notificationPreferenceService->isEnabled($user, 'system.enabled')) {
            return;
        }

        $notification = $createNotificationAction->execute(
            $user,
            $this->title,
            $this->message,
            $this->data,
        );

        event(new NotificationCreated(
            notificationId: $notification->id,
            notifiableId: (int) $notification->notifiable_id,
            type: (string) $notification->type,
            title: data_get($notification->data, 'title'),
            message: data_get($notification->data, 'message'),
            actorId: null,
            occurredAt: now()->toIso8601String(),
        ));

        if ($notificationPreferenceService->isEnabled($user, 'realtime.enabled')) {
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

    public function failed(Throwable $exception): void
    {
        Log::error('CreateNotificationJob permanently failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
