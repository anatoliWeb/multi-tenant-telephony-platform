<?php

namespace Tests\Feature\Api;

use App\Enums\Rbac\PermissionScope;
use App\Enums\Rbac\RoleScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantAwareRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_permissions_resolve_without_active_tenant(): void
    {
        $permission = Permission::create([
            'name' => 'sample.view',
            'scope' => PermissionScope::Platform->value,
            'scope_reference' => PermissionScope::Platform->value,
            'description' => 'Sample view',
        ]);

        $role = Role::create([
            'name' => 'sample_platform_role',
            'scope' => RoleScope::Platform->value,
            'scope_reference' => RoleScope::Platform->value,
            'description' => 'Sample platform role',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'name' => 'Platform User',
            'email' => 'platform@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->roles()->sync([$role->id]);

        /** @var PermissionCacheService $cache */
        $cache = app(PermissionCacheService::class);

        $this->assertSame(['sample.view'], $cache->getPlatformPermissionsForUser($user));
        $this->assertSame(['sample.view'], $cache->getEffectivePermissionsForUser($user));
    }

    public function test_tenant_permissions_resolve_only_for_active_tenant_context(): void
    {
        $tenants = app(TenantBootstrapService::class)->ensureBaseTenants();
        /** @var Tenant $tenant */
        $tenant = $tenants['default'];

        $permission = Permission::create([
            'name' => 'sample.view',
            'scope' => PermissionScope::Tenant->value,
            'scope_reference' => PermissionScope::Tenant->value,
            'description' => 'Sample view',
        ]);

        $role = Role::create([
            'name' => 'sample_tenant_role',
            'scope' => RoleScope::Tenant->value,
            'scope_reference' => (string) $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'description' => 'Sample tenant role',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->getKey(),
                'scope_reference' => (string) $tenant->getKey(),
            ],
        ]);

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($tenant);

        /** @var PermissionCacheService $cache */
        $cache = app(PermissionCacheService::class);

        $this->assertSame(['sample.view'], $cache->getTenantPermissionsForUser($user, $tenant));
        $this->assertSame(['sample.view'], $cache->getEffectivePermissionsForUser($user));
    }

    public function test_tenant_role_does_not_authorize_platform_routes(): void
    {
        $tenants = app(TenantBootstrapService::class)->ensureBaseTenants();
        /** @var Tenant $tenant */
        $tenant = $tenants['default'];

        $permission = Permission::create([
            'name' => 'users.view',
            'scope' => PermissionScope::Tenant->value,
            'scope_reference' => PermissionScope::Tenant->value,
            'description' => 'Users view',
        ]);

        $role = Role::create([
            'name' => 'tenant_viewer',
            'scope' => RoleScope::Tenant->value,
            'scope_reference' => (string) $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'description' => 'Tenant viewer',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'name' => 'Tenant Only',
            'email' => 'tenant-only@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->getKey(),
                'scope_reference' => (string) $tenant->getKey(),
            ],
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->id,
            'status' => 'active',
            'activated_at' => now(),
        ]);

        app(TenantContext::class)->setTenant($tenant);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')
            ->assertForbidden();
    }
}

