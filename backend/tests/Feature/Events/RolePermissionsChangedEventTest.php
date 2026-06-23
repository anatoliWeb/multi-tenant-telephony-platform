<?php

namespace Tests\Feature\Events;

use App\Events\Rbac\RolePermissionsChanged;
use App\Listeners\Rbac\InvalidatePermissionCache;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RolePermissionsChangedEventTest extends TestCase
{
    public function test_event_service_provider_registers_role_permissions_changed_listener(): void
    {
        $this->assertTrue(Event::hasListeners(RolePermissionsChanged::class));
    }

    public function test_role_permissions_changed_payload_shape_stays_stable(): void
    {
        $event = new RolePermissionsChanged(
            roleId: 10,
            roleName: 'manager',
            permissionNames: ['users.view', 'users.edit'],
            actorId: 7,
            occurredAt: now()->toIso8601String(),
        );

        $this->assertSame(10, $event->roleId);
        $this->assertSame('manager', $event->roleName);
        $this->assertSame(['users.view', 'users.edit'], $event->permissionNames);
        $this->assertSame(7, $event->actorId);
        $this->assertIsString($event->occurredAt);
    }

    public function test_dispatch_role_permissions_changed_invalidates_permission_cache(): void
    {
        /** @var PermissionCacheService $cacheService */
        $cacheService = app(PermissionCacheService::class);
        $beforeVersion = $cacheService->globalVersion();

        event(new RolePermissionsChanged(
            roleId: 10,
            roleName: 'manager',
            permissionNames: ['users.view'],
            actorId: 1,
            occurredAt: now()->toIso8601String(),
        ));

        $this->assertGreaterThan($beforeVersion, $cacheService->globalVersion());
    }

    public function test_permission_cache_invalidation_listener_uses_after_commit_contract(): void
    {
        $this->assertContains(
            ShouldHandleEventsAfterCommit::class,
            class_implements(InvalidatePermissionCache::class)
        );
    }
}
