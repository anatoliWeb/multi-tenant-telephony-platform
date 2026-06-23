<?php

namespace Tests\Feature\Events;

use App\Events\Rbac\PermissionChanged;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PermissionChangedEventTest extends TestCase
{
    public function test_event_service_provider_registers_permission_changed_listener(): void
    {
        $this->assertTrue(Event::hasListeners(PermissionChanged::class));
    }

    public function test_permission_changed_payload_shape_stays_stable(): void
    {
        $event = new PermissionChanged(
            permissionId: 11,
            permissionName: 'users.view',
            changeType: 'updated',
            actorId: 5,
            occurredAt: now()->toIso8601String(),
        );

        $this->assertSame(11, $event->permissionId);
        $this->assertSame('users.view', $event->permissionName);
        $this->assertSame('updated', $event->changeType);
        $this->assertSame(5, $event->actorId);
        $this->assertIsString($event->occurredAt);
    }

    public function test_dispatch_permission_changed_invalidates_permission_cache(): void
    {
        /** @var PermissionCacheService $cacheService */
        $cacheService = app(PermissionCacheService::class);
        $beforeVersion = $cacheService->globalVersion();

        event(new PermissionChanged(
            permissionId: 11,
            permissionName: 'users.view',
            changeType: 'updated',
            actorId: 5,
            occurredAt: now()->toIso8601String(),
        ));

        $this->assertGreaterThan($beforeVersion, $cacheService->globalVersion());
    }
}
