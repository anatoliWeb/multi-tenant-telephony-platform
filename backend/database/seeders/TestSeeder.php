<?php

namespace Database\Seeders;

use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Seeding\RbacSeedService;
use App\Services\Seeding\SeederEnvironmentService;
use App\Services\Tenancy\TenantBootstrapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class TestSeeder extends Seeder
{
    public function run(): void
    {
        $environment = app(SeederEnvironmentService::class);
        if (! $environment->isTesting()) {
            throw new RuntimeException('TestSeeder may only run in the testing environment.');
        }

        $environment->assertNotProduction('test seeding');
        $environment->assertSafeTestingDatabase(
            config('database.connections.mysql.database'),
            'test seeding'
        );

        $rbacSeed = app(RbacSeedService::class);
        $permissions = $rbacSeed->seedPermissionCatalog();
        $platformRoles = $rbacSeed->seedPlatformRoles();
        $tenants = app(TenantBootstrapService::class)->ensureBaseTenants();
        $tenantRoles = [];

        foreach ($tenants as $tenant) {
            $tenantRoles[$tenant->id] = $rbacSeed->seedTenantRoles($tenant);
        }

        $rbacSeed->syncPermissions($platformRoles['platform_super_admin'], $permissions['platform']);
        $rbacSeed->syncPermissions($platformRoles['platform_support'], $permissions['platform_support']);
        $rbacSeed->syncPermissions($platformRoles['admin'], $permissions['platform_admin']);
        $rbacSeed->syncPermissions($platformRoles['manager'], ['users.view', 'users.edit']);
        $rbacSeed->syncPermissions($platformRoles['user'], ['users.view']);

        $defaultTenant = $tenants['default'];
        $secondaryTenant = $tenants['secondary'];
        $suspendedTenant = $tenants['suspended'];

        $platformAdmin = $this->upsertUser('test-platform-admin@test.local', 'Test Platform Admin');
        $tenantOwner = $this->upsertUser('test-tenant-owner@test.local', 'Test Tenant Owner');
        $tenantAdmin = $this->upsertUser('test-tenant-admin@test.local', 'Test Tenant Admin');
        $tenantAgent = $this->upsertUser('test-tenant-agent@test.local', 'Test Tenant Agent');
        $multiTenantUser = $this->upsertUser('test-multi-tenant@test.local', 'Test Multi Tenant');
        $tenantAOnlyUser = $this->upsertUser('test-tenant-a-only@test.local', 'Test Tenant A Only');
        $tenantBOnlyUser = $this->upsertUser('test-tenant-b-only@test.local', 'Test Tenant B Only');
        $suspendedMembershipUser = $this->upsertUser('test-suspended-membership@test.local', 'Test Suspended Membership');

        $rbacSeed->assignPlatformRoles($platformAdmin, [$platformRoles['platform_super_admin']]);

        $this->assignMembership($defaultTenant, $tenantOwner, TenantMembershipStatus::Active);
        $rbacSeed->assignTenantRole($tenantOwner, $tenantRoles[$defaultTenant->id]['owner'], $defaultTenant);

        $this->assignMembership($secondaryTenant, $tenantAdmin, TenantMembershipStatus::Active);
        $rbacSeed->assignTenantRole($tenantAdmin, $tenantRoles[$secondaryTenant->id]['admin'], $secondaryTenant);

        $this->assignMembership($defaultTenant, $tenantAgent, TenantMembershipStatus::Active);
        $rbacSeed->assignTenantRole($tenantAgent, $tenantRoles[$defaultTenant->id]['agent'], $defaultTenant);

        $this->assignMembership($defaultTenant, $multiTenantUser, TenantMembershipStatus::Active);
        $this->assignMembership($secondaryTenant, $multiTenantUser, TenantMembershipStatus::Active);
        $rbacSeed->assignTenantRole($multiTenantUser, $tenantRoles[$defaultTenant->id]['owner'], $defaultTenant);
        $rbacSeed->assignTenantRole($multiTenantUser, $tenantRoles[$secondaryTenant->id]['agent'], $secondaryTenant);

        $this->assignMembership($defaultTenant, $tenantAOnlyUser, TenantMembershipStatus::Active);
        $rbacSeed->assignTenantRole($tenantAOnlyUser, $tenantRoles[$defaultTenant->id]['read_only'], $defaultTenant);
        $rbacSeed->assignTenantRole($tenantAOnlyUser, $tenantRoles[$defaultTenant->id]['custom_observer'], $defaultTenant);

        $this->assignMembership($secondaryTenant, $tenantBOnlyUser, TenantMembershipStatus::Active);
        $rbacSeed->assignTenantRole($tenantBOnlyUser, $tenantRoles[$secondaryTenant->id]['read_only'], $secondaryTenant);

        $this->assignMembership($suspendedTenant, $suspendedMembershipUser, TenantMembershipStatus::Suspended);
        $rbacSeed->assignTenantRole($suspendedMembershipUser, $tenantRoles[$suspendedTenant->id]['read_only'], $suspendedTenant);
    }

    protected function upsertUser(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
            ]
        );
    }

    protected function assignMembership(Tenant $tenant, User $user, TenantMembershipStatus $status): TenantMembership
    {
        return TenantMembership::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => $status,
                'invited_by' => null,
                'invited_at' => null,
                'accepted_at' => $status === TenantMembershipStatus::Removed ? null : now(),
                'activated_at' => $status === TenantMembershipStatus::Active ? now() : null,
                'suspended_at' => $status === TenantMembershipStatus::Suspended ? now() : null,
            ]
        );
    }
}
