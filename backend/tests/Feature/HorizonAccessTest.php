<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_horizon_dashboard(): void
    {
        $this->get('/horizon')->assertStatus(302);
    }

    public function test_authenticated_user_without_system_monitoring_permission_cannot_access_horizon(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/horizon')->assertForbidden();
    }

    public function test_authenticated_user_with_system_monitoring_permission_can_access_horizon(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'system.monitoring']);
        $user->permissions()->sync([$permission->id]);

        $this->actingAs($user);

        $this->get('/horizon')->assertOk();
    }

    public function test_user_seeder_registers_system_monitoring_permission(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'name' => 'system.monitoring',
        ]);
    }
}

