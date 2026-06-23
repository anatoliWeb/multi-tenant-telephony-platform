<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_endpoint_returns_roles_permissions_and_current_user_permissions(): void
    {
        $role = Role::create(['name' => 'admin']);
        $view = Permission::create(['name' => 'users.view']);
        $edit = Permission::create(['name' => 'users.edit']);

        $role->permissions()->sync([$view->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->permissions()->sync([$edit->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/meta');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'roles',
                    'permissions',
                    'current_user',
                    'current_user_permissions',
                ],
            ]);

        $permissionNames = $response->json('data.current_user_permissions');

        $this->assertContains('users.view', $permissionNames);
        $this->assertContains('users.edit', $permissionNames);
    }
}
