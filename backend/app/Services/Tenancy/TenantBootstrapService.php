<?php

namespace App\Services\Tenancy;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TenantBootstrapService
{
    public const DEFAULT_TENANT_UUID = '11111111-1111-1111-1111-111111111111';
    public const SECONDARY_TENANT_UUID = '22222222-2222-2222-2222-222222222222';
    public const SUSPENDED_TENANT_UUID = '33333333-3333-3333-3333-333333333333';

    public function ensureBaseTenants(): array
    {
        $now = now();

        $default = Tenant::updateOrCreate(
            ['id' => self::DEFAULT_TENANT_UUID],
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
            ['id' => self::SECONDARY_TENANT_UUID],
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
            ['id' => self::SUSPENDED_TENANT_UUID],
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

            /** @var Tenant $defaultTenant */
            $defaultTenant = $tenants['default'];

            User::query()
                ->with('roles')
                ->orderBy('id')
                ->chunkById(100, function ($users) use ($defaultTenant): void {
                    foreach ($users as $user) {
                        if ($this->isPlatformOnlyUser($user)) {
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

    public function seedDemoMemberships(): void
    {
        DB::transaction(function (): void {
            $tenants = $this->ensureBaseTenants();

            /** @var Tenant $defaultTenant */
            $defaultTenant = $tenants['default'];
            /** @var Tenant $secondaryTenant */
            $secondaryTenant = $tenants['secondary'];
            /** @var Tenant $suspendedTenant */
            $suspendedTenant = $tenants['suspended'];

            $users = User::query()
                ->with('roles')
                ->orderBy('id')
                ->get()
                ->reject(fn (User $user): bool => $this->isPlatformOnlyUser($user))
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

    public function resolveTenantByIdentifier(string $identifier): ?Tenant
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return Tenant::query()
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    public function userHasActiveMembership(User $user, Tenant $tenant): bool
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', TenantMembershipStatus::Active->value)
            ->exists();
    }

    public function activeMembershipFor(User $user, Tenant $tenant): ?TenantMembership
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', TenantMembershipStatus::Active->value)
            ->with(['tenant', 'user', 'invitedBy'])
            ->first();
    }

    protected function upsertMembership(
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
                'status' => $status,
                'invited_by' => null,
                'invited_at' => null,
                'accepted_at' => $status === TenantMembershipStatus::Removed ? null : now(),
                'activated_at' => $activatedAt,
                'suspended_at' => $suspendedAt,
            ]
        );
    }

    protected function isPlatformOnlyUser(User $user): bool
    {
        return $user->roles()
            ->where('name', 'admin')
            ->exists();
    }
}
