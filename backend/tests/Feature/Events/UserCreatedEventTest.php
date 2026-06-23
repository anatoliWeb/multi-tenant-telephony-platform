<?php

namespace Tests\Feature\Events;

use App\Events\Users\UserCreated;
use App\Listeners\Users\LogUserCreatedActivity;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserCreatedEventTest extends TestCase
{
    public function test_event_service_provider_registers_user_created_listener(): void
    {
        $this->assertTrue(Event::hasListeners(UserCreated::class));
    }

    public function test_dispatch_user_created_executes_listener_without_errors(): void
    {
        $responses = event(new UserCreated(
            userId: 777,
            userName: 'Domain User',
            userEmail: 'domain-user@example.com',
            actorId: null,
            occurredAt: now()->toIso8601String(),
        ));

        $this->assertNotEmpty($responses);
    }

    public function test_user_created_listener_uses_after_commit_contract(): void
    {
        $this->assertContains(
            ShouldHandleEventsAfterCommit::class,
            class_implements(LogUserCreatedActivity::class)
        );
    }
}
