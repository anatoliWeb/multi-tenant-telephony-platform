<?php

namespace Tests\Feature\Events;

use App\Events\Notifications\NotificationCreated;
use App\Listeners\Notifications\LogNotificationCreatedActivity;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationLifecycleEventTest extends TestCase
{
    public function test_event_service_provider_registers_notification_created_listener(): void
    {
        $this->assertTrue(Event::hasListeners(NotificationCreated::class));
    }

    public function test_notification_created_payload_shape_stays_stable(): void
    {
        $event = new NotificationCreated(
            notificationId: 'notif-uuid',
            notifiableId: 15,
            type: 'system',
            title: 'Payload title',
            message: 'Payload message',
            actorId: 7,
            occurredAt: now()->toIso8601String(),
        );

        $this->assertSame('notif-uuid', $event->notificationId);
        $this->assertSame(15, $event->notifiableId);
        $this->assertSame('system', $event->type);
        $this->assertSame('Payload title', $event->title);
        $this->assertSame('Payload message', $event->message);
        $this->assertSame(7, $event->actorId);
        $this->assertIsString($event->occurredAt);
    }

    public function test_notification_created_listener_uses_after_commit_contract(): void
    {
        $this->assertContains(
            ShouldHandleEventsAfterCommit::class,
            class_implements(LogNotificationCreatedActivity::class)
        );
    }

    public function test_dispatch_notification_created_executes_listener_without_errors(): void
    {
        $responses = event(new NotificationCreated(
            notificationId: 'notif-dispatch',
            notifiableId: 20,
            type: 'system',
            title: 'Dispatch title',
            message: 'Dispatch message',
            actorId: null,
            occurredAt: now()->toIso8601String(),
        ));

        $this->assertNotEmpty($responses);
    }
}

