<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthorizationRouteHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsWithPermissions(array $permissions = [], array $deniedPermissions = []): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $deniedIds = collect($deniedPermissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        $user->deniedPermissions()->sync($deniedIds);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_settings_index_requires_settings_view_permission(): void
    {
        $this->actingAsWithPermissions([]);

        $this->getJson('/api/v1/settings')->assertForbidden();
    }

    public function test_settings_index_allows_authorized_user(): void
    {
        $this->actingAsWithPermissions(['settings.view']);

        $this->getJson('/api/v1/settings')->assertOk();
    }

    public function test_settings_index_denied_permission_override_returns_forbidden(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'settings-reader']);
        $permission = Permission::firstOrCreate(['name' => 'settings.view']);

        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $user->deniedPermissions()->sync([$permission->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/settings')->assertForbidden();
    }

    public function test_translations_manage_requires_translations_view_permission(): void
    {
        $this->actingAsWithPermissions([]);

        $this->getJson('/api/v1/translations/manage')->assertForbidden();
    }

    public function test_translations_manage_allows_authorized_user(): void
    {
        $this->actingAsWithPermissions(['translations.view']);

        $this->getJson('/api/v1/translations/manage')->assertOk();
    }

    public function test_public_runtime_translation_preload_stays_guest_accessible(): void
    {
        $this->getJson('/api/v1/translations?locale=en')->assertOk();
    }
}

