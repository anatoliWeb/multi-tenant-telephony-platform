<?php

namespace Tests\Feature\Events;

use App\Events\Auth\TokenCreated;
use App\Events\Auth\TokenRevoked;
use App\Listeners\Auth\LogTokenCreatedActivity;
use App\Listeners\Auth\LogTokenRevokedActivity;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TokenLifecycleEventTest extends TestCase
{
    public function test_event_service_provider_registers_token_created_listener(): void
    {
        $this->assertTrue(Event::hasListeners(TokenCreated::class));
    }

    public function test_event_service_provider_registers_token_revoked_listener(): void
    {
        $this->assertTrue(Event::hasListeners(TokenRevoked::class));
    }

    public function test_token_created_payload_shape_stays_stable(): void
    {
        $event = new TokenCreated(
            tokenId: 100,
            tokenName: 'api-token',
            tokenableId: 10,
            actorId: 10,
            abilities: ['users.view'],
            occurredAt: now()->toIso8601String(),
        );

        $this->assertSame(100, $event->tokenId);
        $this->assertSame('api-token', $event->tokenName);
        $this->assertSame(10, $event->tokenableId);
        $this->assertSame(10, $event->actorId);
        $this->assertSame(['users.view'], $event->abilities);
        $this->assertIsString($event->occurredAt);
    }

    public function test_token_revoked_payload_shape_stays_stable(): void
    {
        $event = new TokenRevoked(
            tokenId: 101,
            tokenName: 'logout-token',
            tokenableId: 11,
            actorId: 11,
            revokeReason: 'logout',
            occurredAt: now()->toIso8601String(),
        );

        $this->assertSame(101, $event->tokenId);
        $this->assertSame('logout-token', $event->tokenName);
        $this->assertSame(11, $event->tokenableId);
        $this->assertSame(11, $event->actorId);
        $this->assertSame('logout', $event->revokeReason);
        $this->assertIsString($event->occurredAt);
    }

    public function test_token_lifecycle_listeners_use_after_commit_contract(): void
    {
        $this->assertContains(
            ShouldHandleEventsAfterCommit::class,
            class_implements(LogTokenCreatedActivity::class)
        );

        $this->assertContains(
            ShouldHandleEventsAfterCommit::class,
            class_implements(LogTokenRevokedActivity::class)
        );
    }
}
