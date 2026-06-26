<?php

namespace App\Services\Seeding;

use App\Enums\Contacts\ContactStatus;
use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Enums\TenantMembershipStatus;
use App\Models\Extension;
use App\Models\ExtensionCredential;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Extensions\ExtensionService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantBootstrapService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDemoSeedService
{
    public function __construct(
        protected RbacSeedService $rbacSeedService,
        protected TenantBootstrapService $tenantBootstrapService,
        protected ContactDemoSeedService $contactDemoSeedService,
        protected TenantContext $tenantContext,
        protected ExtensionService $extensionService,
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
            'contacts' => 0,
            'extensions' => 0,
            'phone_numbers' => 0,
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

        $counts['contacts'] += $this->contactDemoSeedService->seed($defaultTenant, $this->contactRows('tenant-a'))['contacts'];
        $counts['contacts'] += $this->contactDemoSeedService->seed($secondaryTenant, $this->contactRows('tenant-b'))['contacts'];
        $counts['extensions'] += $this->seedExtensions($defaultTenant, 'tenant-a');
        $counts['extensions'] += $this->seedExtensions($secondaryTenant, 'tenant-b');
        $counts['phone_numbers'] += $this->seedPhoneNumbers($defaultTenant, 'tenant-a');
        $counts['phone_numbers'] += $this->seedPhoneNumbers($secondaryTenant, 'tenant-b');

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contactRows(string $tenantPrefix): array
    {
        $sharedPhone = $tenantPrefix === 'tenant-a' ? '+15550009999' : '+15550009999';

        return [
            [
                'owner_email' => sprintf('%s-owner@test.local', $tenantPrefix),
                'display_name' => Str::title($tenantPrefix).' Support Contact',
                'first_name' => 'Support',
                'last_name' => 'Contact',
                'company_name' => Str::title($tenantPrefix).' Holdings',
                'job_title' => 'Support Manager',
                'notes' => 'Synthetic tenant contact fixture.',
                'status' => ContactStatus::Active->value,
                'tags' => ['VIP', 'Prospect'],
                'phones' => [
                    [
                        'label' => 'work',
                        'raw_number' => $sharedPhone,
                        'normalized_number' => $sharedPhone,
                        'is_primary' => true,
                        'is_sms_capable' => true,
                    ],
                    [
                        'label' => 'mobile',
                        'raw_number' => $tenantPrefix === 'tenant-a' ? '+15550001111' : '+15550002222',
                        'normalized_number' => $tenantPrefix === 'tenant-a' ? '+15550001111' : '+15550002222',
                        'is_primary' => false,
                        'is_sms_capable' => true,
                    ],
                ],
                'emails' => [
                    [
                        'label' => 'work',
                        'email' => sprintf('%s-support@example.test', $tenantPrefix),
                        'is_primary' => true,
                    ],
                ],
            ],
            [
                'owner_email' => sprintf('%s-admin@test.local', $tenantPrefix),
                'display_name' => Str::title($tenantPrefix).' Archived Vendor',
                'first_name' => 'Archived',
                'last_name' => 'Vendor',
                'company_name' => 'Archive Vendor LLC',
                'job_title' => 'Vendor',
                'notes' => 'Archived synthetic fixture.',
                'status' => ContactStatus::Archived->value,
                'tags' => ['Vendor'],
                'phones' => [
                    [
                        'label' => 'work',
                        'raw_number' => $tenantPrefix === 'tenant-a' ? '+15550003333' : '+15550004444',
                        'normalized_number' => $tenantPrefix === 'tenant-a' ? '+15550003333' : '+15550004444',
                        'is_primary' => true,
                        'is_sms_capable' => false,
                    ],
                ],
                'emails' => [
                    [
                        'label' => 'work',
                        'email' => sprintf('%s-vendor@example.test', $tenantPrefix),
                        'is_primary' => true,
                    ],
                ],
            ],
        ];
    }

    private function seedExtensions(Tenant $tenant, string $tenantPrefix): int
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $agent = User::query()->where('email', sprintf('%s-agent@test.local', $tenantPrefix))->first();

        if (! $owner instanceof User || ! $agent instanceof User) {
            return 0;
        }

        $this->tenantContext->setTenant($tenant);

        try {
            if (! config('telephony.enabled', false)) {
                $this->seedExtensionsWithoutProvisioning($tenant, $owner, $agent, $tenantPrefix);

                return 2;
            }

            if (! Extension::query()->where('tenant_id', $tenant->getKey())->where('number', '2001')->exists()) {
                $this->extensionService->create([
                    'number' => '2001',
                    'label' => Str::title($tenantPrefix).' Support',
                    'assigned_user_id' => $owner->getKey(),
                ], $owner);
            }

            if (! Extension::query()->where('tenant_id', $tenant->getKey())->where('number', '2002')->exists()) {
                $this->extensionService->create([
                    'number' => '2002',
                    'label' => Str::title($tenantPrefix).' Sales',
                    'assigned_user_id' => $agent->getKey(),
                    'status' => 'active',
                ], $owner);
            }
        } finally {
            $this->tenantContext->clear();
        }

        return 2;
    }

    private function seedExtensionsWithoutProvisioning(Tenant $tenant, User $owner, User $agent, string $tenantPrefix): void
    {
        $rows = [
            ['number' => '2001', 'label' => Str::title($tenantPrefix).' Support', 'user' => $owner],
            ['number' => '2002', 'label' => Str::title($tenantPrefix).' Sales', 'user' => $agent],
        ];

        foreach ($rows as $row) {
            $extension = Extension::updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'number' => $row['number'],
                ],
                [
                    'uuid' => $this->stableExtensionUuid($tenant, $row['number']),
                    'label' => $row['label'],
                    'status' => 'active',
                    'provisioning_status' => 'pending',
                    'registration_status' => 'unknown',
                    'assigned_user_id' => $row['user']->getKey(),
                    'created_by' => $owner->getKey(),
                    'updated_by' => $owner->getKey(),
                    'metadata' => [
                        'provider_state' => [
                            'provider' => 'fake',
                            'endpoint_status' => 'not_provisioned',
                            'registration_status' => 'simulated',
                        ],
                    ],
                ]
            );

            ExtensionCredential::updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'extension_id' => $extension->getKey(),
                ],
                [
                    'username' => $row['number'],
                    'secret_encrypted' => encrypt('fixture-secret-'.$tenantPrefix.'-'.$row['number']),
                    'secret_hint' => substr($row['number'], -4),
                    'version' => 1,
                    'rotated_by' => $owner->getKey(),
                    'rotated_at' => now(),
                ]
            );
        }
    }

    private function stableExtensionUuid(Tenant $tenant, string $number): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':extension:'.$number), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function seedPhoneNumbers(Tenant $tenant, string $tenantPrefix): int
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $agent = User::query()->where('email', sprintf('%s-agent@test.local', $tenantPrefix))->first();

        if (! $owner instanceof User) {
            return 0;
        }

        $rows = $tenantPrefix === 'tenant-a'
            ? [
                ['number' => '+15550001001', 'display' => '+1 555 000 1001', 'user' => $owner, 'is_primary' => true],
                ['number' => '+15550001002', 'display' => '+1 555 000 1002', 'user' => $owner, 'is_primary' => false],
                ['number' => '+15550001003', 'display' => '+1 555 000 1003', 'user' => $agent, 'is_primary' => true],
                ['number' => '+15550001999', 'display' => '+1 555 000 1999', 'user' => null, 'is_primary' => false],
            ]
            : [
                ['number' => '+15550001001', 'display' => '+1 555 000 1001', 'user' => $owner, 'is_primary' => true],
            ];

        foreach ($rows as $row) {
            $assignedUser = $row['user'];
            $isAssigned = $assignedUser instanceof User;

            PhoneNumber::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'normalized_number' => $row['number'],
                ],
                [
                    'uuid' => $this->stablePhoneNumberUuid($tenant, $row['number']),
                    'number' => $row['number'],
                    'display_number' => $row['display'],
                    'type' => PhoneNumberType::Did->value,
                    'status' => PhoneNumberStatus::Active->value,
                    'assignment_status' => $isAssigned
                        ? PhoneNumberAssignmentStatus::Assigned->value
                        : PhoneNumberAssignmentStatus::Unassigned->value,
                    'assigned_user_id' => $isAssigned ? $assignedUser->getKey() : null,
                    'is_primary' => $isAssigned ? (bool) $row['is_primary'] : false,
                    'primary_assignment_key' => $isAssigned && (bool) $row['is_primary']
                        ? $tenant->getKey().'#'.$assignedUser->getKey()
                        : null,
                    'provider_name' => 'manual',
                    'provider_reference' => sprintf('%s-%s', $tenantPrefix, substr($row['number'], -4)),
                    'country_code' => '1',
                    'capabilities' => ['voice'],
                    'metadata' => [
                        'inventory_source' => 'demo_seeder',
                    ],
                    'purchased_at' => Carbon::now()->subDays(14),
                    'activated_at' => Carbon::now()->subDays(13),
                    'released_at' => null,
                    'created_by' => $owner->getKey(),
                    'updated_by' => $owner->getKey(),
                ]
            );
        }

        return count($rows);
    }

    private function stablePhoneNumberUuid(Tenant $tenant, string $number): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':phone-number:'.$number), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
