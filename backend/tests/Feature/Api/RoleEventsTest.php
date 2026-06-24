<?php

namespace Tests\Feature\Api;

use App\Events\Rbac\RolePermissionsChanged;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoleEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_service_update_dispatches_role_permissions_changed_event(): void
    {
        $role = Role::create(['name' => 'qa-role', 'scope' => 'platform', 'scope_reference' => 'platform']);
        Permission::firstOrCreate(['name' => 'users.view', 'scope' => 'platform']);
        $actor = User::factory()->create();
        $this->actingAs($actor, 'web');

        Event::fakeFor(function () use ($role): void {
            /** @var RoleService $roleService */
            $roleService = app(RoleService::class);
            $roleService->update($role, [
                'permissions' => ['users.view'],
            ]);

            Event::assertDispatched(RolePermissionsChanged::class, function (RolePermissionsChanged $event): bool {
                return $event->roleName === 'qa-role'
                    && in_array('users.view', $event->permissionNames, true)
                    && $event->actorId !== null;
            });
        });
    }
}
