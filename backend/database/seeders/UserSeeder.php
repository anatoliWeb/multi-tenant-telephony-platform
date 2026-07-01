<?php

namespace Database\Seeders;

use App\Enums\Rbac\PermissionScope;
use App\Enums\Rbac\RoleScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantBootstrapService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\Support\TenantSeedService;

/**
 * Seed full RBAC demo data.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->permissionCatalog();
        $this->seedPermissions($permissions);

        $platformSuperAdminRole = $this->upsertRole('platform_super_admin', RoleScope::Platform, true, true, 'Platform super administrator');
        $platformSupportRole = $this->upsertRole('platform_support', RoleScope::Platform, true, true, 'Platform support operator');
        $adminRole = $this->upsertRole('admin', RoleScope::Platform, true, true, 'Administrator');
        $managerRole = $this->upsertRole('manager', RoleScope::Platform, true, false, 'Manager');
        $userRole = $this->upsertRole('user', RoleScope::Platform, true, false, 'User');

        $tenants = app(TenantSeedService::class)->ensureBaseTenants();
        $tenantRoles = [];

        foreach ($tenants as $tenantKey => $tenant) {
            if (!$tenant instanceof Tenant) {
                continue;
            }

            $tenantRoles[$tenant->id] = [
                'owner' => $this->upsertRole('tenant_owner', RoleScope::Tenant, true, true, 'Tenant owner', $tenant),
                'admin' => $this->upsertRole('tenant_admin', RoleScope::Tenant, true, true, 'Tenant administrator', $tenant),
                'telephony_manager' => $this->upsertRole('telephony_manager', RoleScope::Tenant, true, true, 'Telephony manager', $tenant),
                'team_manager' => $this->upsertRole('team_manager', RoleScope::Tenant, true, true, 'Team manager', $tenant),
                'billing_manager' => $this->upsertRole('billing_manager', RoleScope::Tenant, true, true, 'Billing manager', $tenant),
                'analyst' => $this->upsertRole('analyst', RoleScope::Tenant, true, true, 'Analyst', $tenant),
                'agent' => $this->upsertRole('agent', RoleScope::Tenant, true, true, 'Agent', $tenant),
                'read_only' => $this->upsertRole('read_only', RoleScope::Tenant, true, true, 'Read only', $tenant),
            ];
        }

        $this->syncPermissions($platformSuperAdminRole, $permissions['platform']);
        $this->syncPermissions($platformSupportRole, $permissions['platform_support']);
        $this->syncPermissions($adminRole, $permissions['platform_admin']);
        $this->syncPermissions($managerRole, ['users.view', 'users.edit']);
        $this->syncPermissions($userRole, ['users.view']);

        foreach ($tenantRoles as $tenantId => $roles) {
            $this->syncPermissions($roles['owner'], $permissions['tenant']);
            $this->syncPermissions($roles['admin'], $permissions['tenant_admin']);
            $this->syncPermissions($roles['telephony_manager'], $permissions['tenant_telephony_manager']);
            $this->syncPermissions($roles['team_manager'], $permissions['tenant_team_manager']);
            $this->syncPermissions($roles['billing_manager'], $permissions['tenant_billing_manager']);
            $this->syncPermissions($roles['analyst'], $permissions['tenant_analyst']);
            $this->syncPermissions($roles['agent'], $permissions['tenant_agent']);
            $this->syncPermissions($roles['read_only'], $permissions['tenant_read_only']);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        $this->assignPlatformRoles($admin, [$platformSuperAdminRole, $adminRole]);
        $this->assignTenantRole($admin, $tenantRoles[TenantBootstrapService::DEFAULT_TENANT_UUID]['owner'], $tenants['default']);

        $faker = Faker::create('en_US');
        $faker->seed(20260513);

        $usersViewPermission = Permission::query()
            ->where('name', 'users.view')
            ->where('scope', PermissionScope::Tenant->value)
            ->first();

        for ($i = 1; $i <= 30; $i++) {
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();
            $email = strtolower($firstName.'.'.$lastName.$i.'@test.com');

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$firstName} {$lastName}",
                    'password' => Hash::make('password'),
                ]
            );

            $this->assignPlatformRoles($user, [
                $i <= 3 ? $platformSuperAdminRole : ($i <= 10 ? $managerRole : $userRole),
            ]);

            $defaultTenant = $tenants['default'];
            $secondaryTenant = $tenants['secondary'];
            $suspendedTenant = $tenants['suspended'];

            $defaultTenantRoles = $tenantRoles[$defaultTenant->id];
            $secondaryTenantRoles = $tenantRoles[$secondaryTenant->id];
            $suspendedTenantRoles = $tenantRoles[$suspendedTenant->id];

            if ($i <= 3) {
                $this->assignTenantRole($user, $defaultTenantRoles['owner'], $defaultTenant);
                $this->assignTenantRole($user, $secondaryTenantRoles['admin'], $secondaryTenant);
            } elseif ($i <= 10) {
                $this->assignTenantRole($user, $defaultTenantRoles['admin'], $defaultTenant);
                $this->assignTenantRole($user, $secondaryTenantRoles['agent'], $secondaryTenant);
            } else {
                $this->assignTenantRole($user, $defaultTenantRoles['agent'], $defaultTenant);
            }

            if ($i === 2) {
                $this->assignTenantRole($user, $suspendedTenantRoles['read_only'], $suspendedTenant);
            }

            if ($usersViewPermission && $i % 4 === 0) {
                $user->permissions()->syncWithoutDetaching([$usersViewPermission->id]);
            }

            $tokenCount = $faker->numberBetween(1, 3);
            for ($t = 1; $t <= $tokenCount; $t++) {
                $user->createToken("demo_token_{$i}_{$t}");
            }
        }
    }

    /**
     * @return array{
     *   platform: array<int, string>,
     *   platform_support: array<int, string>,
     *   platform_admin: array<int, string>,
     *   tenant: array<int, string>,
     *   tenant_admin: array<int, string>,
     *   tenant_telephony_manager: array<int, string>,
     *   tenant_team_manager: array<int, string>,
     *   tenant_billing_manager: array<int, string>,
     *   tenant_analyst: array<int, string>,
     *   tenant_agent: array<int, string>,
     *   tenant_read_only: array<int, string>
     * }
     */
    protected function permissionCatalog(): array
    {
        $all = [
            'access_admin',
            'dashboard.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.assign_permissions',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
            'tokens.view',
            'tokens.create',
            'tokens.edit',
            'tokens.delete',
            'api.docs.view',
            'api.docs.view.full',
            'settings.view',
            'settings.edit',
            'activity.view',
            'system.monitoring',
            'translations.view',
            'translations.create',
            'translations.edit',
            'translations.delete',
            'notifications.view',
            'notifications.create',
            'notifications.delete',
            'notifications.preferences',
            'tenants.view',
            'chat.view',
            'chat.create',
            'chat.send',
            'chat.edit',
            'chat.delete',
            'chat.conversations.view',
            'chat.conversations.create',
            'chat.conversations.edit',
            'chat.conversations.close',
            'chat.conversations.archive',
            'chat.conversations.delete',
            'chat.participants.view',
            'chat.participants.add',
            'chat.participants.remove',
            'chat.participants.manage',
            'chat.attachments.view',
            'chat.attachments.upload',
            'chat.attachments.download',
            'chat.attachments.delete',
            'chat.admin.view',
            'chat.admin.reply',
            'chat.admin.moderate',
            'chat.admin.delete_messages',
            'chat.admin.close_conversations',
            'chat.admin.view_metadata',
            'chat.external_api.use',
            'chat.external_api.manage',
            'chat.external_api.view_logs',
            'chat.webhooks.view',
            'chat.webhooks.create',
            'chat.webhooks.edit',
            'chat.webhooks.delete',
            'chat.webhooks.manage',
            'chat.webhooks.view_deliveries',
            'chat.webhooks.retry_deliveries',
        ];

        foreach ($all as $permission) {
            foreach ([PermissionScope::Platform->value, PermissionScope::Tenant->value] as $scope) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'scope' => $scope],
                    [
                        'scope_reference' => $scope,
                        'description' => ucfirst(str_replace('.', ' ', $permission)),
                    ]
                );
            }
        }

        return [
            'platform' => $all,
            'platform_support' => [
                'users.view',
                'roles.view',
                'permissions.view',
                'tokens.view',
                'api.docs.view',
                'activity.view',
                'system.monitoring',
                'tenants.view',
            ],
            'platform_admin' => $all,
            'tenant' => array_values(array_diff($all, [
                'roles.create',
                'roles.edit',
                'roles.delete',
                'roles.assign_permissions',
                'permissions.view',
                'permissions.create',
                'permissions.edit',
                'permissions.delete',
                'tokens.view',
                'tokens.create',
                'tokens.edit',
                'tokens.delete',
                'api.docs.view',
                'api.docs.view.full',
                'settings.view',
                'settings.edit',
                'activity.view',
                'system.monitoring',
                'translations.view',
                'translations.create',
                'translations.edit',
                'translations.delete',
                'chat.admin.view',
                'chat.admin.reply',
                'chat.admin.moderate',
                'chat.admin.delete_messages',
                'chat.admin.close_conversations',
                'chat.admin.view_metadata',
                'chat.external_api.use',
                'chat.external_api.manage',
                'chat.external_api.view_logs',
                'chat.webhooks.view',
                'chat.webhooks.create',
                'chat.webhooks.edit',
                'chat.webhooks.delete',
                'chat.webhooks.manage',
                'chat.webhooks.view_deliveries',
                'chat.webhooks.retry_deliveries',
            ])),
            'tenant_admin' => [
                'dashboard.view',
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'roles.view',
                'chat.view',
                'chat.create',
                'chat.send',
                'chat.edit',
                'chat.delete',
                'chat.conversations.view',
                'chat.conversations.create',
                'chat.conversations.edit',
                'chat.conversations.close',
                'chat.conversations.archive',
                'chat.conversations.delete',
                'chat.participants.view',
                'chat.participants.add',
                'chat.participants.remove',
                'chat.participants.manage',
                'chat.attachments.view',
                'chat.attachments.upload',
                'chat.attachments.download',
                'chat.attachments.delete',
                'notifications.view',
                'notifications.create',
                'notifications.delete',
                'notifications.preferences',
            ],
            'tenant_telephony_manager' => [
                'dashboard.view',
                'users.view',
                'chat.view',
                'chat.send',
                'chat.conversations.view',
                'chat.conversations.create',
                'chat.participants.view',
                'chat.attachments.view',
                'notifications.view',
            ],
            'tenant_team_manager' => [
                'dashboard.view',
                'users.view',
                'users.edit',
                'chat.view',
                'chat.send',
                'chat.conversations.view',
                'chat.participants.view',
                'notifications.view',
            ],
            'tenant_billing_manager' => [
                'dashboard.view',
                'users.view',
                'settings.view',
                'notifications.view',
            ],
            'tenant_analyst' => [
                'dashboard.view',
                'users.view',
                'activity.view',
                'notifications.view',
                'chat.view',
                'chat.conversations.view',
            ],
            'tenant_agent' => [
                'dashboard.view',
                'users.view',
                'chat.view',
                'chat.send',
                'chat.conversations.view',
                'chat.conversations.create',
                'chat.participants.view',
                'chat.attachments.view',
                'notifications.view',
            ],
            'tenant_read_only' => [
                'dashboard.view',
                'users.view',
                'chat.view',
                'chat.conversations.view',
                'chat.participants.view',
                'chat.attachments.view',
                'notifications.view',
            ],
        ];
    }

    protected function upsertRole(
        string $name,
        RoleScope $scope,
        bool $isSystem,
        bool $isProtected,
        string $description,
        ?Tenant $tenant = null
    ): Role {
        $scopeReference = $tenant?->getKey() ?? $scope->value;

        return Role::updateOrCreate(
            ['scope_reference' => $scopeReference, 'name' => $name],
            [
                'scope' => $scope->value,
                'tenant_id' => $tenant?->getKey(),
                'description' => $description,
                'is_system' => $isSystem,
                'is_protected' => $isProtected,
            ]
        );
    }

    protected function seedPermissions(array $catalog): void
    {
        // Catalog entries are deterministic and idempotent; duplicate names are
        // intentionally separated by scope so platform and tenant catalogs can
        // evolve independently.
        foreach ([PermissionScope::Platform->value, PermissionScope::Tenant->value] as $scope) {
            foreach ($catalog['platform'] as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'scope' => $scope],
                    [
                        'scope_reference' => $scope,
                        'description' => ucfirst(str_replace('.', ' ', $permission)),
                    ]
                );
            }
        }
    }

    /**
     * @param array<int, string> $permissionNames
     */
    protected function syncPermissions(Role $role, array $permissionNames): void
    {
        $scope = $role->scope instanceof \BackedEnum ? $role->scope->value : (string) $role->scope;

        $permissionIds = Permission::query()
            ->where('scope', $scope)
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    protected function assignPlatformRoles(User $user, array $roles): void
    {
        foreach ($roles as $role) {
            if (!$role instanceof Role) {
                continue;
            }

            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'tenant_id' => null,
                    'scope_reference' => RoleScope::Platform->value,
                ],
            ]);
        }
    }

    protected function assignTenantRole(User $user, Role $role, Tenant $tenant): void
    {
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->getKey(),
                'scope_reference' => (string) $tenant->getKey(),
            ],
        ]);
    }
}
