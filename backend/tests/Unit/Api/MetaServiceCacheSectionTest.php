<?php

namespace Tests\Unit\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Services\MetaCacheService;
use App\Services\MetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetaServiceCacheSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrupt_roles_cache_rebuilds_roles_section_only(): void
    {
        Role::query()->create(['name' => 'admin', 'description' => 'Admin']);
        Permission::query()->create(['name' => 'users.view', 'description' => 'View users']);

        $rbacVersion = app(MetaCacheService::class)->rbacVersion();
        $rolesKey = sprintf('meta:rbac:roles:v%d', $rbacVersion);
        $permissionsKey = sprintf('meta:rbac:permissions:v%d', $rbacVersion);

        Cache::put($rolesKey, [['broken' => true]], now()->addMinutes(10));
        $sentinelPermissions = [
            [
                'id' => Permission::query()->first()->id,
                'name' => 'users.view',
                'description' => 'View users',
            ],
        ];
        Cache::put($permissionsKey, $sentinelPermissions, now()->addMinutes(10));

        $meta = app(MetaService::class)->getRbacMeta();

        $this->assertCount(1, $meta['roles']);
        $this->assertSame('admin', data_get($meta['roles']->first(), 'name'));
        $this->assertSame('users.view', data_get($meta['permissions']->first(), 'name'));
        $this->assertSame($sentinelPermissions[0]['id'], data_get(Cache::get($permissionsKey), '0.id'));
    }

    public function test_corrupt_permissions_cache_rebuilds_permissions_section_only(): void
    {
        Role::query()->create(['name' => 'admin', 'description' => 'Admin']);
        Permission::query()->create(['name' => 'users.view', 'description' => 'View users']);

        $rbacVersion = app(MetaCacheService::class)->rbacVersion();
        $rolesKey = sprintf('meta:rbac:roles:v%d', $rbacVersion);
        $permissionsKey = sprintf('meta:rbac:permissions:v%d', $rbacVersion);

        $sentinelRoles = [
            [
                'id' => Role::query()->first()->id,
                'name' => 'admin',
                'description' => 'Admin',
            ],
        ];
        Cache::put($rolesKey, $sentinelRoles, now()->addMinutes(10));
        Cache::put($permissionsKey, [['broken' => true]], now()->addMinutes(10));

        $meta = app(MetaService::class)->getRbacMeta();

        $this->assertCount(1, $meta['permissions']);
        $this->assertSame('users.view', data_get($meta['permissions']->first(), 'name'));
        $this->assertSame('admin', data_get($meta['roles']->first(), 'name'));
        $this->assertSame($sentinelRoles[0]['id'], data_get(Cache::get($rolesKey), '0.id'));
    }
}
