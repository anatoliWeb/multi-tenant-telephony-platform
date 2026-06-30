<?php

namespace App\Services\Seeding;

use App\Enums\Rbac\PermissionScope;
use App\Enums\Rbac\RoleScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MetaCacheService;
use App\Services\Rbac\PermissionCacheService;

class RbacSeedService
{
    public function __construct(
        protected PermissionCacheService $permissionCacheService,
        protected MetaCacheService $metaCacheService,
    ) {
    }

    /**
     * Seed the scope-aware permission catalog.
     *
     * WHY:
     * Platform and tenant catalogs intentionally share names while remaining
     * isolated by scope.
     *
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
    public function seedPermissionCatalog(): array
    {
        $catalog = $this->permissionCatalog();

        foreach ($catalog['all'] as $permission) {
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
            'platform' => $catalog['platform'],
            'platform_support' => $catalog['platform_support'],
            'platform_admin' => $catalog['platform_admin'],
            'tenant' => $catalog['tenant'],
            'tenant_admin' => $catalog['tenant_admin'],
            'tenant_telephony_manager' => $catalog['tenant_telephony_manager'],
            'tenant_team_manager' => $catalog['tenant_team_manager'],
            'tenant_billing_manager' => $catalog['tenant_billing_manager'],
            'tenant_analyst' => $catalog['tenant_analyst'],
            'tenant_agent' => $catalog['tenant_agent'],
            'tenant_read_only' => $catalog['tenant_read_only'],
        ];
    }

    /**
     * Seed protected platform roles.
     *
     * @return array<string, Role>
     */
    public function seedPlatformRoles(): array
    {
        return [
            'platform_super_admin' => $this->upsertRole('platform_super_admin', RoleScope::Platform, true, true, 'Platform super administrator'),
            'platform_support' => $this->upsertRole('platform_support', RoleScope::Platform, true, true, 'Platform support operator'),
            'admin' => $this->upsertRole('admin', RoleScope::Platform, true, true, 'Administrator'),
            'manager' => $this->upsertRole('manager', RoleScope::Platform, true, false, 'Manager'),
            'user' => $this->upsertRole('user', RoleScope::Platform, true, false, 'User'),
        ];
    }

    /**
     * Seed tenant roles for a specific tenant.
     *
     * @return array<string, Role>
     */
    public function seedTenantRoles(Tenant $tenant): array
    {
        return [
            'owner' => $this->upsertRole('tenant_owner', RoleScope::Tenant, true, true, 'Tenant owner', $tenant),
            'admin' => $this->upsertRole('tenant_admin', RoleScope::Tenant, true, true, 'Tenant administrator', $tenant),
            'telephony_manager' => $this->upsertRole('telephony_manager', RoleScope::Tenant, true, true, 'Telephony manager', $tenant),
            'team_manager' => $this->upsertRole('team_manager', RoleScope::Tenant, true, true, 'Team manager', $tenant),
            'billing_manager' => $this->upsertRole('billing_manager', RoleScope::Tenant, true, true, 'Billing manager', $tenant),
            'analyst' => $this->upsertRole('analyst', RoleScope::Tenant, true, true, 'Analyst', $tenant),
            'agent' => $this->upsertRole('agent', RoleScope::Tenant, true, true, 'Agent', $tenant),
            'read_only' => $this->upsertRole('read_only', RoleScope::Tenant, true, true, 'Read only', $tenant),
            'custom_observer' => $this->upsertRole('custom_observer', RoleScope::Tenant, false, false, 'Custom demo observer', $tenant),
        ];
    }

    /**
     * @param array<int, string> $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): void
    {
        $scope = $role->scope instanceof \BackedEnum ? $role->scope->value : (string) $role->scope;

        $permissionIds = Permission::query()
            ->where('scope', $scope)
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    /**
     * @param array<string, Role> $roles
     * @param array{
     *   tenant: array<int, string>,
     *   tenant_admin: array<int, string>,
     *   tenant_telephony_manager: array<int, string>,
     *   tenant_team_manager: array<int, string>,
     *   tenant_billing_manager: array<int, string>,
     *   tenant_analyst: array<int, string>,
     *   tenant_agent: array<int, string>,
     *   tenant_read_only: array<int, string>
     * } $permissions
     */
    public function syncTenantRolePermissions(Tenant $tenant, array $roles, array $permissions): void
    {
        $this->syncPermissions($roles['owner'], $permissions['tenant']);
        $this->syncPermissions($roles['admin'], $permissions['tenant_admin']);
        $this->syncPermissions($roles['telephony_manager'], $permissions['tenant_telephony_manager']);
        $this->syncPermissions($roles['team_manager'], $permissions['tenant_team_manager']);
        $this->syncPermissions($roles['billing_manager'], $permissions['tenant_billing_manager']);
        $this->syncPermissions($roles['analyst'], $permissions['tenant_analyst']);
        $this->syncPermissions($roles['agent'], $permissions['tenant_agent']);
        $this->syncPermissions($roles['read_only'], $permissions['tenant_read_only']);

        // Keep the demo-only custom observer role intentionally empty so
        // tenant-specific custom-role workflows can be exercised safely.
        $this->syncPermissions($roles['custom_observer'], []);

        $this->permissionCacheService->forgetForTenant((string) $tenant->getKey());
    }

    public function invalidateRbacCaches(): void
    {
        $this->permissionCacheService->forgetAll();
        $this->metaCacheService->bumpRbacVersion();
    }

    /**
     * @param array<int, Role> $roles
     */
    public function assignPlatformRoles(User $user, array $roles): void
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

    public function assignTenantRole(User $user, Role $role, Tenant $tenant): void
    {
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->getKey(),
                'scope_reference' => (string) $tenant->getKey(),
            ],
        ]);
    }

    /**
     * @return array{
     *   all: array<int, string>,
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
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'contacts.import',
            'contacts.export',
            'contacts.manage_tags',
            'extensions.view',
            'extensions.create',
            'extensions.update',
            'extensions.delete',
            'extensions.manage_credentials',
            'ring_groups.view',
            'ring_groups.create',
            'ring_groups.update',
            'ring_groups.delete',
            'ring_groups.manage_members',
            'ring_groups.test_route',
            'call_queues.view',
            'call_queues.create',
            'call_queues.update',
            'call_queues.delete',
            'call_queues.manage_members',
            'call_queues.pause_members',
            'call_queues.test_route',
            'phone_numbers.view',
            'phone_numbers.create',
            'phone_numbers.update',
            'phone_numbers.delete',
            'phone_numbers.assign',
            'phone_numbers.set_primary',
            'phone_numbers.provision',
            'phone_numbers.release',
            'call_logs.view',
            'call_logs.view_own',
            'call_logs.view_all',
            'call_logs.export',
            'call_logs.view_statistics',
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

        return [
            'all' => $all,
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
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'contacts.delete',
                'contacts.import',
                'contacts.export',
                'contacts.manage_tags',
                'extensions.view',
                'extensions.create',
                'extensions.update',
                'extensions.delete',
                'extensions.manage_credentials',
                'ring_groups.view',
                'ring_groups.create',
                'ring_groups.update',
                'ring_groups.delete',
                'ring_groups.manage_members',
                'ring_groups.test_route',
                'call_queues.view',
                'call_queues.create',
                'call_queues.update',
                'call_queues.delete',
                'call_queues.manage_members',
                'call_queues.pause_members',
                'call_queues.test_route',
                'phone_numbers.view',
                'phone_numbers.create',
                'phone_numbers.update',
                'phone_numbers.delete',
                'phone_numbers.assign',
                'phone_numbers.set_primary',
                'phone_numbers.provision',
                'phone_numbers.release',
                'call_logs.view',
                'call_logs.view_own',
                'call_logs.view_all',
                'call_logs.export',
                'call_logs.view_statistics',
            ],
            'tenant_telephony_manager' => [
                'dashboard.view',
                'users.view',
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'contacts.export',
                'contacts.manage_tags',
                'extensions.view',
                'extensions.create',
                'extensions.update',
                'extensions.manage_credentials',
                'ring_groups.view',
                'ring_groups.create',
                'ring_groups.update',
                'ring_groups.delete',
                'ring_groups.manage_members',
                'ring_groups.test_route',
                'call_queues.view',
                'call_queues.create',
                'call_queues.update',
                'call_queues.delete',
                'call_queues.manage_members',
                'call_queues.pause_members',
                'call_queues.test_route',
                'phone_numbers.view',
                'phone_numbers.create',
                'phone_numbers.update',
                'phone_numbers.delete',
                'phone_numbers.assign',
                'phone_numbers.set_primary',
                'phone_numbers.provision',
                'phone_numbers.release',
                'call_logs.view',
                'call_logs.view_own',
                'call_logs.view_all',
                'call_logs.view_statistics',
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
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'contacts.export',
                'contacts.manage_tags',
                'extensions.view',
                'extensions.update',
                'ring_groups.view',
                'ring_groups.manage_members',
                'call_queues.view',
                'call_queues.manage_members',
                'call_queues.pause_members',
                'phone_numbers.view',
                'phone_numbers.assign',
                'phone_numbers.set_primary',
                'call_logs.view',
                'call_logs.view_own',
                'call_logs.view_all',
                'call_logs.view_statistics',
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
                'contacts.view',
                'extensions.view',
                'phone_numbers.view',
                'call_logs.view',
                'call_logs.view_statistics',
            ],
            'tenant_analyst' => [
                'dashboard.view',
                'users.view',
                'activity.view',
                'notifications.view',
                'contacts.view',
                'contacts.export',
                'extensions.view',
                'ring_groups.view',
                'call_queues.view',
                'phone_numbers.view',
                'call_logs.view',
                'call_logs.view_all',
                'call_logs.view_statistics',
                'chat.view',
                'chat.conversations.view',
            ],
            'tenant_agent' => [
                'dashboard.view',
                'users.view',
                'contacts.view',
                'contacts.create',
                'contacts.update',
                'extensions.view',
                'ring_groups.view',
                'call_queues.view',
                'call_queues.pause_members',
                'phone_numbers.view',
                'call_logs.view',
                'call_logs.view_own',
                'call_logs.view_statistics',
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
                'contacts.view',
                'extensions.view',
                'ring_groups.view',
                'call_queues.view',
                'phone_numbers.view',
                'call_logs.view',
                'call_logs.view_own',
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
}
