<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Create a database notification for a user.
 *
 * WHY:
 * Encapsulates a single write operation that can be reused by different
 * services/workflows without coupling them to notification persistence details.
 */
class CreateNotificationAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(User $user, string $title, string $message, array $data = []): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'system',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'data' => [
                'title' => $title,
                'message' => $message,
                ...$data,
            ],
            'read_at' => null,
        ]);
    }
}

