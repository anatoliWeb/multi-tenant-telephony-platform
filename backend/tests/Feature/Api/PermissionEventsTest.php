<?php

namespace Tests\Feature\Api;

use App\Events\Rbac\PermissionChanged;
use App\Models\Permission;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PermissionEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_service_create_dispatches_permission_changed_event(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor, 'web');

        Event::fakeFor(function (): void {
            /** @var PermissionService $permissionService */
            $permissionService = app(PermissionService::class);
            $permissionService->create([
                'name' => 'reports.export',
                'description' => 'Reports export',
                'translations' => [],
            ]);

            Event::assertDispatched(PermissionChanged::class, function (PermissionChanged $event): bool {
                return $event->permissionName === 'reports.export'
                    && $event->changeType === 'created'
                    && $event->actorId !== null;
            });
        });
    }

    public function test_permission_service_update_dispatches_permission_changed_event(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor, 'web');

        $permission = Permission::create([
            'name' => 'reports.audit',
            'description' => 'Old description',
        ]);

        Event::fakeFor(function () use ($permission): void {
            /** @var PermissionService $permissionService */
            $permissionService = app(PermissionService::class);
            $permissionService->update($permission, [
                'description' => 'Updated description',
                'translations' => [],
            ]);

            Event::assertDispatched(PermissionChanged::class, function (PermissionChanged $event): bool {
                return $event->permissionName === 'reports.audit'
                    && $event->changeType === 'updated';
            });
        });
    }

    public function test_permission_service_change_clears_effective_permission_cache(): void
    {
        /** @var PermissionCacheService $cacheService */
        $cacheService = app(PermissionCacheService::class);
        $beforeVersion = $cacheService->globalVersion();

        $permission = Permission::create([
            'name' => 'users.manage',
            'description' => 'Users manage',
        ]);

        /** @var PermissionService $permissionService */
        $permissionService = app(PermissionService::class);
        $permissionService->update($permission, [
            'description' => 'Users manage updated',
            'translations' => [],
        ]);

        $this->assertGreaterThan($beforeVersion, $cacheService->globalVersion());
    }
}
