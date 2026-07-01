<?php

namespace Database\Seeders\Support;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantBootstrapService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSeedService
{
    /**
     * @return array{default: Tenant, secondary: Tenant, suspended: Tenant}
     */
    public function ensureBaseTenants(): array
    {
        $now = now();

        $default = Tenant::updateOrCreate(
            ['id' => TenantBootstrapService::DEFAULT_TENANT_UUID],
            [
                'name' => 'Default Tenant',
                'slug' => 'default-tenant',
                'status' => TenantStatus::Active,
                'timezone' => 'Europe/Kiev',
                'locale' => 'en',
                'currency' => 'USD',
                'settings' => [
                    'is_default' => true,
                    'type' => 'default',
                ],
                'activated_at' => $now,
                'suspended_at' => null,
            ]
        );

        $secondary = Tenant::updateOrCreate(
            ['id' => TenantBootstrapService::SECONDARY_TENANT_UUID],
            [
                'name' => 'Secondary Tenant',
                'slug' => 'secondary-tenant',
                'status' => TenantStatus::Active,
                'timezone' => 'Europe/Kiev',
                'locale' => 'en',
                'currency' => 'USD',
                'settings' => [
                    'type' => 'secondary',
                ],
                'activated_at' => $now,
                'suspended_at' => null,
            ]
        );

        $suspended = Tenant::updateOrCreate(
            ['id' => TenantBootstrapService::SUSPENDED_TENANT_UUID],
            [
                'name' => 'Suspended Tenant',
                'slug' => 'suspended-tenant',
                'status' => TenantStatus::Suspended,
                'timezone' => 'Europe/Kiev',
                'locale' => 'en',
                'currency' => 'USD',
                'settings' => [
                    'type' => 'suspended',
                ],
                'activated_at' => $now,
                'suspended_at' => $now,
            ]
        );

        return [
            'default' => $default,
            'secondary' => $secondary,
            'suspended' => $suspended,
        ];
    }

    public function backfillExistingUsers(): void
    {
        DB::transaction(function (): void {
            $tenants = $this->ensureBaseTenants();
            $defaultTenant = $tenants['default'];

            User::query()
                ->with('roles')
                ->orderBy('id')
                ->chunkById(100, function ($users) use ($defaultTenant): void {
                    foreach ($users as $user) {
                        if ($user->isPlatformAdmin()) {
                            continue;
                        }

                        $this->upsertMembership(
                            tenant: $defaultTenant,
                            user: $user,
                            status: TenantMembershipStatus::Active,
                            activatedAt: now(),
                            suspendedAt: null
                        );
                    }
                });
        });
    }

    public function seedLegacyDemoMemberships(): void
    {
        DB::transaction(function (): void {
            $tenants = $this->ensureBaseTenants();
            $defaultTenant = $tenants['default'];
            $secondaryTenant = $tenants['secondary'];
            $suspendedTenant = $tenants['suspended'];

            $users = User::query()
                ->with('roles')
                ->orderBy('id')
                ->get()
                ->reject(fn (User $user): bool => $user->isPlatformAdmin())
                ->values();

            foreach ($users as $user) {
                $this->upsertMembership(
                    tenant: $defaultTenant,
                    user: $user,
                    status: TenantMembershipStatus::Active,
                    activatedAt: now(),
                    suspendedAt: null
                );
            }

            $firstUser = $users->get(0);
            if ($firstUser instanceof User) {
                $this->upsertMembership(
                    tenant: $secondaryTenant,
                    user: $firstUser,
                    status: TenantMembershipStatus::Active,
                    activatedAt: now(),
                    suspendedAt: null
                );
            }

            $secondUser = $users->get(1);
            if ($secondUser instanceof User) {
                $this->upsertMembership(
                    tenant: $suspendedTenant,
                    user: $secondUser,
                    status: TenantMembershipStatus::Suspended,
                    activatedAt: now(),
                    suspendedAt: now()
                );
            }
        });
    }

    public function upsertMembership(
        Tenant $tenant,
        User $user,
        TenantMembershipStatus $status,
        ?Carbon $activatedAt,
        ?Carbon $suspendedAt
    ): TenantMembership {
        return TenantMembership::query()->updateOrCreate(
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
                'activated_at' => $activatedAt,
                'suspended_at' => $suspendedAt,
            ]
        );
    }
}
