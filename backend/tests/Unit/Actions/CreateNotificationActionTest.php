<?php

namespace Tests\Unit\Actions;

use App\Actions\Notifications\CreateNotificationAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class CreateNotificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_database_notification_for_user(): void
    {
        $user = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->execute(
            user: $user,
            title: 'System Alert',
            message: 'Settings were updated.',
            data: ['source' => 'settings'],
        );

        $this->assertInstanceOf(DatabaseNotification::class, $notification);
        $this->assertSame('system', $notification->type);
        $this->assertNull($notification->read_at);
        $this->assertSame($user::class, $notification->notifiable_type);
        $this->assertSame($user->id, $notification->notifiable_id);
        $this->assertSame('System Alert', $notification->data['title'] ?? null);
        $this->assertSame('Settings were updated.', $notification->data['message'] ?? null);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'type' => 'system',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'read_at' => null,
        ]);
    }
}

