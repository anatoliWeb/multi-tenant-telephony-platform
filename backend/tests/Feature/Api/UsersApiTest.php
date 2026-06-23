<?php

namespace Tests\Feature\Api;

use App\Events\Users\UserCreated;
use App\Events\Users\UserUpdated;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_users_index_requires_users_view_permission(): void
    {
        $this->actingAsWithPermissions([]);

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_users_index_returns_data_when_authorized(): void
    {
        User::factory()->count(2)->create();
        $this->actingAsWithPermissions(['users.view']);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles'],
                ],
            ]);
    }

    public function test_user_show_returns_404_for_unknown_user(): void
    {
        $this->actingAsWithPermissions(['users.view']);

        $this->getJson('/api/users/999999')->assertNotFound();
    }

    public function test_user_can_be_created_with_roles_and_direct_permissions(): void
    {
        $this->actingAsWithPermissions(['users.create']);

        $role = Role::create(['name' => 'manager']);
        Permission::firstOrCreate(['name' => 'users.view']);
        Permission::firstOrCreate(['name' => 'users.edit']);

        $payload = [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'secret123',
            'roles' => [$role->id],
            'permissions' => ['users.view', 'users.edit'],
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'created@example.com')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'roles', 'permissions'],
            ]);

        $createdId = $response->json('data.id');
        $createdUser = User::findOrFail($createdId);

        $this->assertTrue($createdUser->roles()->where('roles.id', $role->id)->exists());
        $this->assertTrue($createdUser->permissions()->where('name', 'users.view')->exists());
        $this->assertTrue($createdUser->permissions()->where('name', 'users.edit')->exists());
    }

    public function test_user_create_dispatches_user_created_domain_event(): void
    {
        $this->actingAsWithPermissions(['users.create']);

        $payload = [
            'name' => 'Event Target User',
            'email' => 'event-target@example.com',
            'password' => 'secret123',
            'roles' => [],
            'permissions' => [],
            'denied_permissions' => [],
        ];

        Event::fakeFor(function () use ($payload): void {
            $response = $this->postJson('/api/users', $payload);
            $response->assertCreated();

            Event::assertDispatched(UserCreated::class, function (UserCreated $event) use ($payload): bool {
                return $event->userEmail === $payload['email']
                    && $event->userName === $payload['name']
                    && $event->actorId !== null;
            });
        });
    }

    public function test_user_create_validates_payload(): void
    {
        $this->actingAsWithPermissions(['users.create']);

        $this->postJson('/api/users', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_be_updated_with_optional_password(): void
    {
        $this->actingAsWithPermissions(['users.edit']);

        $user = User::factory()->create([
            'email' => 'before@example.com',
            'password' => bcrypt('old-password'),
        ]);
        $role = Role::create(['name' => 'user']);
        Permission::firstOrCreate(['name' => 'users.view']);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'after@example.com',
            'roles' => [$role->id],
            'permissions' => ['users.view'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'after@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'after@example.com',
        ]);
    }

    public function test_user_update_dispatches_user_updated_domain_event(): void
    {
        $this->actingAsWithPermissions(['users.edit']);

        $user = User::factory()->create([
            'name' => 'Before Event Update',
            'email' => 'before-event-update@example.com',
        ]);

        Event::fakeFor(function () use ($user): void {
            $response = $this->putJson("/api/users/{$user->id}", [
                'name' => 'After Event Update',
                'email' => 'after-event-update@example.com',
                'roles' => [],
                'permissions' => [],
                'denied_permissions' => [],
            ]);

            $response->assertOk();

            Event::assertDispatched(UserUpdated::class, function (UserUpdated $event): bool {
                return $event->userName === 'After Event Update'
                    && $event->userEmail === 'after-event-update@example.com'
                    && in_array('name', $event->changedFields, true)
                    && in_array('email', $event->changedFields, true)
                    && !in_array('password', $event->changedFields, true);
            });
        });
    }

    public function test_user_update_requires_users_edit_permission(): void
    {
        $target = User::factory()->create();
        $this->actingAsWithPermissions(['users.view']);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'No Access',
            'email' => 'no-access@example.com',
        ])->assertForbidden();
    }

    public function test_user_delete_requires_users_delete_permission(): void
    {
        $target = User::factory()->create();
        $this->actingAsWithPermissions(['users.view']);

        $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
    }

    public function test_user_can_be_deleted_when_authorized(): void
    {
        $target = User::factory()->create();
        $this->actingAsWithPermissions(['users.delete']);

        $this->deleteJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_denied_permission_overrides_role_permission_for_protected_endpoint(): void
    {
        User::factory()->count(2)->create();

        $user = User::factory()->create();
        $role = Role::create(['name' => 'auditor']);
        $permission = Permission::firstOrCreate(['name' => 'users.view']);

        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $user->permissions()->sync([]);
        $user->deniedPermissions()->sync([$permission->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_protected_endpoint_allows_access_when_denied_permission_removed(): void
    {
        User::factory()->count(2)->create();

        $user = User::factory()->create();
        $operator = User::factory()->create();
        $role = Role::create(['name' => 'auditor']);
        $permission = Permission::firstOrCreate(['name' => 'users.view']);

        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $user->deniedPermissions()->sync([$permission->id]);

        Sanctum::actingAs($user);
        $this->getJson('/api/users')->assertForbidden();

        Sanctum::actingAs($operator);
        app(UserService::class)->update($user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [$role->id],
            'permissions' => [],
            'denied_permissions' => [],
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles'],
                ],
            ]);
    }
}
