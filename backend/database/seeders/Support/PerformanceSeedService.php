<?php

namespace Database\Seeders\Support;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PerformanceSeedService
{
    public function __construct(
        protected RbacSeedService $rbacSeedService,
        protected SeederEnvironmentService $environmentService,
    ) {
    }

    /**
     * Seed high-volume tenant-distributed demo data.
     *
     * @return array<string, int|float>
     */
    public function seed(int $tenantCount = 3, int $usersPerTenant = 150, bool $allowProduction = false): array
    {
        $this->environmentService->assertNotProduction('performance seeding', $allowProduction);

        $startedAt = microtime(true);

        $permissions = $this->rbacSeedService->seedPermissionCatalog();
        $this->rbacSeedService->seedPlatformRoles();

        $tenants = $this->seedTenants(max(1, $tenantCount));
        $roleMap = [];
        foreach ($tenants as $tenant) {
            $roleMap[$tenant->id] = $this->rbacSeedService->seedTenantRoles($tenant);
            $this->rbacSeedService->syncTenantRolePermissions($tenant, $roleMap[$tenant->id], $permissions);
        }

        $this->rbacSeedService->invalidateRbacCaches();

        $userCount = 0;
        $membershipCount = 0;
        $roleAssignmentCount = 0;

        foreach ($tenants as $tenantIndex => $tenant) {
            $roleSet = $roleMap[$tenant->id];
            $userCount += $this->seedUsersForTenant($tenant, $roleSet, max(1, $usersPerTenant), $tenantIndex + 1, $membershipCount, $roleAssignmentCount);
        }

        return [
            'tenants' => count($tenants),
            'users' => $userCount,
            'memberships' => $membershipCount,
            'role_assignments' => $roleAssignmentCount,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * @return array<int, Tenant>
     */
    protected function seedTenants(int $tenantCount): array
    {
        $tenants = [];

        for ($index = 1; $index <= $tenantCount; $index++) {
            $slug = sprintf('performance-tenant-%02d', $index);
            $tenants[] = Tenant::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => sprintf('Performance Tenant %02d', $index),
                    'status' => $index === $tenantCount && $tenantCount > 2 ? TenantStatus::Suspended->value : TenantStatus::Active->value,
                    'timezone' => 'Europe/Kiev',
                    'locale' => 'en',
                    'currency' => 'USD',
                    'settings' => [
                        'seed' => 'performance',
                        'index' => $index,
                    ],
                    'activated_at' => now(),
                    'suspended_at' => $index === $tenantCount && $tenantCount > 2 ? now() : null,
                ]
            );
        }

        return $tenants;
    }

    /**
     * Seed tenant-distributed users and assignments.
     *
     * @param array<string, \App\Models\Role> $roleSet
     */
    protected function seedUsersForTenant(
        Tenant $tenant,
        array $roleSet,
        int $usersPerTenant,
        int $tenantIndex,
        int &$membershipCount,
        int &$roleAssignmentCount,
    ): int {
        $rows = [];
        $emails = [];
        $now = now();

        for ($index = 1; $index <= $usersPerTenant; $index++) {
            $email = sprintf('perf-%02d-%04d@test.local', $tenantIndex, $index);
            $emails[] = $email;
            $rows[] = [
                'name' => sprintf('Perf %02d User %04d', $tenantIndex, $index),
                'email' => $email,
                'password' => Hash::make('password'),
                'email_verified_at' => $now,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insertOrIgnore($rows);

        $userIds = DB::table('users')
            ->whereIn('email', $emails)
            ->pluck('id', 'email')
            ->all();

        $membershipRows = [];
        $roleRows = [];

        foreach ($emails as $index => $email) {
            $userId = $userIds[$email] ?? null;
            if (! $userId) {
                continue;
            }

            $role = match (($index + 1) % 4) {
                0 => $roleSet['owner'],
                1 => $roleSet['agent'],
                2 => $roleSet['analyst'],
                default => $roleSet['read_only'],
            };

            $membershipRows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->getKey(),
                'user_id' => $userId,
                'status' => TenantMembershipStatus::Active->value,
                'invited_by' => null,
                'invited_at' => null,
                'accepted_at' => $now,
                'activated_at' => $now,
                'suspended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $roleRows[] = [
                'user_id' => $userId,
                'role_id' => $role->id,
                'scope_reference' => (string) $tenant->getKey(),
                'tenant_id' => $tenant->getKey(),
            ];
        }

        DB::table('tenant_memberships')->upsert(
            $membershipRows,
            ['tenant_id', 'user_id'],
            ['status', 'invited_by', 'invited_at', 'accepted_at', 'activated_at', 'suspended_at', 'updated_at']
        );

        DB::table('role_user')->upsert(
            $roleRows,
            ['user_id', 'role_id', 'scope_reference'],
            ['tenant_id']
        );

        $membershipCount += count($membershipRows);
        $roleAssignmentCount += count($roleRows);

        return count($rows);
    }
}
