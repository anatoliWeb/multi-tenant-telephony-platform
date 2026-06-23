<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\MetaCacheService;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSessionMetaRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_meta_returns_401_quickly(): void
    {
        $response = $this->getJson('/api/v1/meta');

        $response->assertStatus(401);
    }

    public function test_session_authenticated_admin_runtime_endpoints_return_200(): void
    {
        $user = User::factory()->create([
            'email' => 'admin-runtime@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], [
            'description' => 'Administrator',
        ]);

        $permissions = collect([
            Permission::query()->firstOrCreate(['name' => 'notifications.view'], [
                'group' => 'notifications',
                'action' => 'view',
                'description' => 'View notifications',
            ]),
            Permission::query()->firstOrCreate(['name' => 'permissions.view'], [
                'group' => 'permissions',
                'action' => 'view',
                'description' => 'View permissions',
            ]),
            Permission::query()->firstOrCreate(['name' => 'users.view'], [
                'group' => 'users',
                'action' => 'view',
                'description' => 'View users',
            ]),
        ]);

        $user->permissions()->sync($permissions->pluck('id')->all());
        $adminRole->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->sync([$adminRole->id]);
        app(PermissionCacheService::class)->forgetForUser($user);
        app(MetaCacheService::class)->bumpRbacVersion();

        $login = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'admin-runtime@example.com',
            'password' => 'secret123',
            'remember' => true,
        ]);
        $login->assertOk();

        $metaResponse = $this->getJson('/api/v1/meta')->assertOk();

        $roles = $metaResponse->json('data.roles', []);
        $this->assertIsArray($roles);
        $this->assertNotEmpty($roles);
        foreach ($roles as $role) {
            $this->assertIsArray($role);
            $this->assertNotNull($role['id'] ?? null);
            $this->assertNotSame('', $role['name'] ?? '');
            $this->assertNotSame('Illuminate\\Database\\Eloquent\\Collection', $role['name'] ?? '');
        }

        $permissionsPayload = $metaResponse->json('data.permissions', []);
        $this->assertIsArray($permissionsPayload);
        $this->assertNotEmpty($permissionsPayload);
        foreach ($permissionsPayload as $permissionPayload) {
            $this->assertIsArray($permissionPayload);
            $this->assertNotNull($permissionPayload['id'] ?? null);
            $this->assertNotSame('', $permissionPayload['name'] ?? '');
            $this->assertNotSame('Illuminate\\Database\\Eloquent\\Collection', $permissionPayload['name'] ?? '');
        }

        $this->assertContains('users.view', $metaResponse->json('data.role_permissions.admin', []));
        $this->assertSame('admin', data_get($metaResponse->json(), 'data.current_user.roles.0.name'));
        $this->assertContains('users.view', $metaResponse->json('data.current_user_permissions', []));

        $bootstrapResponse = $this->getJson('/api/v1/meta/bootstrap')->assertOk()
            ->assertJsonStructure([
                'data' => ['current_user', 'current_user_permissions'],
            ]);
        $bootstrapResponse
            ->assertJsonMissingPath('data.roles')
            ->assertJsonMissingPath('data.permissions')
            ->assertJsonMissingPath('data.role_permissions');
        $this->getJson('/api/v1/meta/rbac')->assertOk()
            ->assertJsonStructure([
                'data' => ['roles', 'permissions', 'role_permissions'],
            ]);
        $this->getJson('/api/v1/stats')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertOk();
        $this->getJson('/api/v1/permissions')->assertOk();
    }
}
