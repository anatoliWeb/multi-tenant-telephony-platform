<?php

namespace App\Services\Seeding;

use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantBootstrapService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDemoSeedService
{
    public function __construct(
        protected RbacSeedService $rbacSeedService,
        protected TenantBootstrapService $tenantBootstrapService,
    ) {
    }

    /**
     * Seed deterministic demo tenants, users, memberships and tenant roles.
     *
     * @return array<string, int>
     */
    public function seed(): array
    {
        $tenants = $this->tenantBootstrapService->ensureBaseTenants();
        $platformRoles = $this->rbacSeedService->seedPlatformRoles();
        $tenantRoles = [];

        foreach ($tenants as $tenant) {
            if ($tenant instanceof Tenant) {
                $tenantRoles[$tenant->id] = $this->rbacSeedService->seedTenantRoles($tenant);
            }
        }

        $counts = [
            'tenants' => count($tenants),
            'users' => 0,
            'memberships' => 0,
            'role_assignments' => 0,
        ];

        $platformAdmin = $this->upsertUser('platform-admin@test.local', 'Platform Admin');
        $platformSupport = $this->upsertUser('platform-support@test.local', 'Platform Support');
        $this->rbacSeedService->assignPlatformRoles($platformAdmin, [
            $platformRoles['platform_super_admin'],
            $platformRoles['admin'],
        ]);
        $this->rbacSeedService->assignPlatformRoles($platformSupport, [
            $platformRoles['platform_support'],
        ]);

        $counts['users'] += 2;
        $counts['role_assignments'] += 3;

        $defaultTenant = $tenants['default'];
        $secondaryTenant = $tenants['secondary'];
        $suspendedTenant = $tenants['suspended'];

        $scenarioUsers = $this->seedTenantPersonaMatrix($defaultTenant, $tenantRoles[$defaultTenant->id], 'tenant-a');
        $counts['users'] += $scenarioUsers['users'];
        $counts['memberships'] += $scenarioUsers['memberships'];
        $counts['role_assignments'] += $scenarioUsers['role_assignments'];

        $scenarioUsers = $this->seedTenantPersonaMatrix($secondaryTenant, $tenantRoles[$secondaryTenant->id], 'tenant-b', true);
        $counts['users'] += $scenarioUsers['users'];
        $counts['memberships'] += $scenarioUsers['memberships'];
        $counts['role_assignments'] += $scenarioUsers['role_assignments'];

        $multiTenantUser = $this->upsertUser('multi-tenant-user@test.local', 'Multi Tenant User');
        $this->assignMembership($defaultTenant, $multiTenantUser, TenantMembershipStatus::Active);
        $this->assignMembership($secondaryTenant, $multiTenantUser, TenantMembershipStatus::Active);
        $this->rbacSeedService->assignTenantRole($multiTenantUser, $tenantRoles[$defaultTenant->id]['owner'], $defaultTenant);
        $this->rbacSeedService->assignTenantRole($multiTenantUser, $tenantRoles[$secondaryTenant->id]['agent'], $secondaryTenant);
        $counts['users']++;
        $counts['memberships'] += 2;
        $counts['role_assignments'] += 2;

        $suspendedMembershipUser = $this->upsertUser('suspended-membership@test.local', 'Suspended Membership User');
        $this->assignMembership($defaultTenant, $suspendedMembershipUser, TenantMembershipStatus::Suspended);
        $this->rbacSeedService->assignTenantRole($suspendedMembershipUser, $tenantRoles[$defaultTenant->id]['read_only'], $defaultTenant);
        $counts['users']++;
        $counts['memberships']++;
        $counts['role_assignments']++;

        $suspendedTenantUser = $this->upsertUser('suspended-tenant@test.local', 'Suspended Tenant User');
        $this->assignMembership($suspendedTenant, $suspendedTenantUser, TenantMembershipStatus::Active);
        $this->rbacSeedService->assignTenantRole($suspendedTenantUser, $tenantRoles[$suspendedTenant->id]['read_only'], $suspendedTenant);
        $counts['users']++;
        $counts['memberships']++;
        $counts['role_assignments']++;

        // Keep a custom tenant-owned role in the demo dataset so tenant-specific
        // role creation is exercised without reusing the same role across tenants.
        $customObserverUser = $this->upsertUser('tenant-a-observer@test.local', 'Tenant A Observer');
        $this->assignMembership($defaultTenant, $customObserverUser, TenantMembershipStatus::Active);
        $customObserver = $tenantRoles[$defaultTenant->id]['custom_observer'];
        $this->rbacSeedService->assignTenantRole($customObserverUser, $customObserver, $defaultTenant);
        $counts['memberships']++;
        $counts['role_assignments']++;

        return $counts;
    }

    /**
     * Seed personas for one tenant.
     *
     * @param array<string, \App\Models\Role> $tenantRoles
     *
     * @return array{users:int,memberships:int,role_assignments:int}
     */
    public function seedTenantPersonaMatrix(Tenant $tenant, array $tenantRoles, string $slugPrefix, bool $includeOwner = true): array
    {
        $blueprints = [
            ['key' => 'owner', 'name' => 'Owner', 'role' => 'owner'],
            ['key' => 'admin', 'name' => 'Admin', 'role' => 'admin'],
            ['key' => 'telephony', 'name' => 'Telephony Manager', 'role' => 'telephony_manager'],
            ['key' => 'team', 'name' => 'Team Manager', 'role' => 'team_manager'],
            ['key' => 'billing', 'name' => 'Billing Manager', 'role' => 'billing_manager'],
            ['key' => 'analyst', 'name' => 'Analyst', 'role' => 'analyst'],
            ['key' => 'agent', 'name' => 'Agent', 'role' => 'agent'],
            ['key' => 'readonly', 'name' => 'Read Only', 'role' => 'read_only'],
        ];

        $created = [
            'users' => 0,
            'memberships' => 0,
            'role_assignments' => 0,
        ];

        foreach ($blueprints as $index => $blueprint) {
            if (! $includeOwner && $blueprint['role'] === 'owner') {
                continue;
            }

            $email = sprintf('%s-%s@test.local', $slugPrefix, $blueprint['key']);
            $user = $this->upsertUser($email, sprintf('%s %s', $tenant->name, $blueprint['name']));

            $status = $blueprint['role'] === 'readonly'
                ? TenantMembershipStatus::Active
                : TenantMembershipStatus::Active;

            $this->assignMembership($tenant, $user, $status);
            $this->rbacSeedService->assignTenantRole($user, $tenantRoles[$blueprint['role']], $tenant);

            $created['users']++;
            $created['memberships']++;
            $created['role_assignments']++;
        }

        return $created;
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
