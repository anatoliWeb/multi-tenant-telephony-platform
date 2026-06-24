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
        TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->id,
            'status' => 'active',
            'activated_at' => now(),
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

    public function test_tenant_permission_cache_is_isolated_between_tenants_and_switches_cleanly(): void
    {
        $tenants = app(TenantBootstrapService::class)->ensureBaseTenants();
        /** @var Tenant $tenantA */
        $tenantA = $tenants['default'];
        /** @var Tenant $tenantB */
        $tenantB = $tenants['secondary'];

        $permissionA = Permission::create([
            'name' => 'tenant-a.view',
            'scope' => PermissionScope::Tenant->value,
            'scope_reference' => PermissionScope::Tenant->value,
            'description' => 'Tenant A view',
        ]);
        $permissionB = Permission::create([
            'name' => 'tenant-b.view',
            'scope' => PermissionScope::Tenant->value,
            'scope_reference' => PermissionScope::Tenant->value,
            'description' => 'Tenant B view',
        ]);

        $roleA = Role::create([
            'name' => 'tenant_a_viewer',
            'scope' => RoleScope::Tenant->value,
            'scope_reference' => (string) $tenantA->getKey(),
            'tenant_id' => $tenantA->getKey(),
            'description' => 'Tenant A viewer',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $roleA->permissions()->sync([$permissionA->id]);

        $roleB = Role::create([
            'name' => 'tenant_b_viewer',
            'scope' => RoleScope::Tenant->value,
            'scope_reference' => (string) $tenantB->getKey(),
            'tenant_id' => $tenantB->getKey(),
            'description' => 'Tenant B viewer',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $roleB->permissions()->sync([$permissionB->id]);

        $user = User::create([
            'name' => 'Multi Tenant User',
            'email' => 'multi-tenant@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->roles()->syncWithoutDetaching([
            $roleA->id => [
                'tenant_id' => $tenantA->getKey(),
                'scope_reference' => (string) $tenantA->getKey(),
            ],
            $roleB->id => [
                'tenant_id' => $tenantB->getKey(),
                'scope_reference' => (string) $tenantB->getKey(),
            ],
        ]);
        TenantMembership::create([
            'tenant_id' => $tenantA->getKey(),
            'user_id' => $user->id,
            'status' => 'active',
            'activated_at' => now(),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenantB->getKey(),
            'user_id' => $user->id,
            'status' => 'active',
            'activated_at' => now(),
        ]);

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);
        /** @var PermissionCacheService $cache */
        $cache = app(PermissionCacheService::class);

        $tenantContext->setTenant($tenantA);
        $this->assertSame(['tenant-a.view'], $cache->getTenantPermissionsForUser($user, $tenantA));
        $this->assertSame(['tenant-a.view'], $cache->getEffectivePermissionsForUser($user));

        $tenantContext->setTenant($tenantB);
        $this->assertSame(['tenant-b.view'], $cache->getTenantPermissionsForUser($user, $tenantB));
        $this->assertSame(['tenant-b.view'], $cache->getEffectivePermissionsForUser($user));

        $tenantContext->setTenant($tenantA);
        $this->assertSame(['tenant-a.view'], $cache->getEffectivePermissionsForUser($user));

        $this->assertNotSame(
            $cache->getTenantPermissionsForUser($user, $tenantA),
            $cache->getTenantPermissionsForUser($user, $tenantB)
        );
    }
}
