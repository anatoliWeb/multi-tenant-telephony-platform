<?php

namespace Tests\Feature\Events;

use App\Events\Users\UserUpdated;
use App\Listeners\Users\LogUserUpdatedActivity;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserUpdatedEventTest extends TestCase
{
    public function test_event_service_provider_registers_user_updated_listener(): void
    {
        $this->assertTrue(Event::hasListeners(UserUpdated::class));
    }

    public function test_dispatch_user_updated_executes_listener_without_errors(): void
    {
        $responses = event(new UserUpdated(
            userId: 778,
            userName: 'Updated Domain User',
            userEmail: 'updated-domain-user@example.com',
            actorId: null,
            changedFields: ['name', 'email'],
            occurredAt: now()->toIso8601String(),
        ));

        $this->assertNotEmpty($responses);
    }

    public function test_user_updated_payload_shape_stays_stable(): void
    {
        $event = new UserUpdated(
            userId: 100,
            userName: 'Payload User',
            userEmail: 'payload-user@example.com',
            actorId: 12,
            changedFields: ['name'],
            occurredAt: now()->toIso8601String(),
        );

        $this->assertSame(100, $event->userId);
        $this->assertSame('Payload User', $event->userName);
        $this->assertSame('payload-user@example.com', $event->userEmail);
        $this->assertSame(12, $event->actorId);
        $this->assertSame(['name'], $event->changedFields);
        $this->assertIsString($event->occurredAt);
    }

    public function test_user_updated_listener_uses_after_commit_contract(): void
    {
        $this->assertContains(
            ShouldHandleEventsAfterCommit::class,
            class_implements(LogUserUpdatedActivity::class)
        );
    }
}
