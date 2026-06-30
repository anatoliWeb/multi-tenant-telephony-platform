<?php

namespace App\Services\Seeding;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\CallLogs\CallEventType;
use App\Enums\CallQueues\CallQueueMemberType;
use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\CallQueues\CallQueueStrategy;
use App\Enums\Contacts\ContactStatus;
use App\Enums\Ivr\IvrActionType;
use App\Enums\Ivr\IvrDestinationType;
use App\Enums\Ivr\IvrMenuStatus;
use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Enums\RingGroups\RingGroupMemberType;
use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\RingGroups\RingGroupStrategy;
use App\Enums\TenantMembershipStatus;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\CallEvent;
use App\Models\CallLog;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\Contact;
use App\Models\IvrMenu;
use App\Models\IvrOption;
use App\Models\Extension;
use App\Models\ExtensionCredential;
use App\Models\QueueMemberPause;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Extensions\ExtensionService;
use App\Services\Seeding\TenantSeedService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDemoSeedService
{
    public function __construct(
        protected RbacSeedService $rbacSeedService,
        protected TenantSeedService $tenantSeedService,
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
        $tenants = $this->tenantSeedService->ensureBaseTenants();
        $permissions = $this->rbacSeedService->seedPermissionCatalog();
        $platformRoles = $this->rbacSeedService->seedPlatformRoles();
        $tenantRoles = [];

        foreach ($tenants as $tenant) {
            if ($tenant instanceof Tenant) {
                $tenantRoles[$tenant->id] = $this->rbacSeedService->seedTenantRoles($tenant);
                $this->rbacSeedService->syncTenantRolePermissions($tenant, $tenantRoles[$tenant->id], $permissions);
            }
        }

        $this->rbacSeedService->invalidateRbacCaches();

        $counts = [
            'tenants' => count($tenants),
            'users' => 0,
            'memberships' => 0,
            'role_assignments' => 0,
            'contacts' => 0,
            'extensions' => 0,
            'ring_groups' => 0,
            'ring_group_members' => 0,
            'call_queues' => 0,
            'call_queue_members' => 0,
            'queue_member_pauses' => 0,
            'ivr_menus' => 0,
            'ivr_options' => 0,
            'phone_numbers' => 0,
            'call_logs' => 0,
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
        $ringGroupCounts = $this->seedRingGroups($defaultTenant, 'tenant-a');
        $counts['ring_groups'] += $ringGroupCounts['ring_groups'];
        $counts['ring_group_members'] += $ringGroupCounts['ring_group_members'];
        $ringGroupCounts = $this->seedRingGroups($secondaryTenant, 'tenant-b');
        $counts['ring_groups'] += $ringGroupCounts['ring_groups'];
        $counts['ring_group_members'] += $ringGroupCounts['ring_group_members'];
        $queueCounts = $this->seedCallQueues($defaultTenant, 'tenant-a');
        $counts['call_queues'] += $queueCounts['call_queues'];
        $counts['call_queue_members'] += $queueCounts['call_queue_members'];
        $counts['queue_member_pauses'] += $queueCounts['queue_member_pauses'];
        $queueCounts = $this->seedCallQueues($secondaryTenant, 'tenant-b');
        $counts['call_queues'] += $queueCounts['call_queues'];
        $counts['call_queue_members'] += $queueCounts['call_queue_members'];
        $counts['queue_member_pauses'] += $queueCounts['queue_member_pauses'];
        $ivrCounts = $this->seedIvrMenus($defaultTenant, 'tenant-a');
        $counts['ivr_menus'] += $ivrCounts['ivr_menus'];
        $counts['ivr_options'] += $ivrCounts['ivr_options'];
        $ivrCounts = $this->seedIvrMenus($secondaryTenant, 'tenant-b');
        $counts['ivr_menus'] += $ivrCounts['ivr_menus'];
        $counts['ivr_options'] += $ivrCounts['ivr_options'];
        $counts['phone_numbers'] += $this->seedPhoneNumbers($defaultTenant, 'tenant-a');
        $counts['phone_numbers'] += $this->seedPhoneNumbers($secondaryTenant, 'tenant-b');
        $counts['call_logs'] += $this->seedCallLogs($defaultTenant, 'tenant-a');
        $counts['call_logs'] += $this->seedCallLogs($secondaryTenant, 'tenant-b');
        $counts['call_logs'] += $this->seedCallLogVolume($defaultTenant, 'tenant-a', 500);
        $counts['call_logs'] += $this->seedCallLogVolume($secondaryTenant, 'tenant-b', 500);

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

    /**
     * @return array{ring_groups:int, ring_group_members:int}
     */
    private function seedRingGroups(Tenant $tenant, string $tenantPrefix): array
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $agent = User::query()->where('email', sprintf('%s-agent@test.local', $tenantPrefix))->first();

        if (! $owner instanceof User || ! $agent instanceof User) {
            return [
                'ring_groups' => 0,
                'ring_group_members' => 0,
            ];
        }

        $created = [
            'ring_groups' => 0,
            'ring_group_members' => 0,
        ];

        $groups = [
            [
                'slug' => 'sales-ring-group',
                'name' => Str::title($tenantPrefix).' Sales Ring Group',
                'description' => 'Simultaneous sales routing group.',
                'strategy' => RingGroupStrategy::Simultaneous->value,
                'status' => RingGroupStatus::Active->value,
                'members' => [
                    ['member_type' => RingGroupMemberType::Extension->value, 'extension_number' => '2001', 'priority' => 1, 'delay_seconds' => 0, 'timeout_seconds' => 20],
                    ['member_type' => RingGroupMemberType::User->value, 'user_email' => sprintf('%s-agent@test.local', $tenantPrefix), 'priority' => 1, 'delay_seconds' => 0, 'timeout_seconds' => 20],
                ],
            ],
            [
                'slug' => 'support-ring-group',
                'name' => Str::title($tenantPrefix).' Support Ring Group',
                'description' => 'Sequential support routing group.',
                'strategy' => RingGroupStrategy::Sequential->value,
                'status' => RingGroupStatus::Active->value,
                'members' => [
                    ['member_type' => RingGroupMemberType::User->value, 'user_email' => sprintf('%s-owner@test.local', $tenantPrefix), 'priority' => 1, 'delay_seconds' => 0, 'timeout_seconds' => 20],
                    ['member_type' => RingGroupMemberType::Extension->value, 'extension_number' => '2002', 'priority' => 2, 'delay_seconds' => 5, 'timeout_seconds' => 25],
                ],
            ],
            [
                'slug' => 'after-hours-ring-group',
                'name' => Str::title($tenantPrefix).' After Hours Ring Group',
                'description' => 'Inactive placeholder group for empty-state testing.',
                'strategy' => RingGroupStrategy::Random->value,
                'status' => RingGroupStatus::Suspended->value,
                'members' => [],
            ],
        ];

        $this->tenantContext->setTenant($tenant);

        try {
            foreach ($groups as $groupRow) {
                $ringGroup = RingGroup::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'slug' => $groupRow['slug'],
                    ],
                    [
                        'uuid' => $this->stableRingGroupUuid($tenant, $groupRow['slug']),
                        'name' => $groupRow['name'],
                        'description' => $groupRow['description'],
                        'strategy' => $groupRow['strategy'],
                        'status' => $groupRow['status'],
                        'ring_timeout_seconds' => $groupRow['strategy'] === RingGroupStrategy::Sequential->value ? 15 : 20,
                        'max_ring_duration_seconds' => 90,
                        'failover_destination_type' => null,
                        'failover_destination_id' => null,
                        'settings' => [
                            'demo' => true,
                            'inventory_source' => 'demo_seeder',
                        ],
                        'metadata' => [
                            'inventory_source' => 'demo_seeder',
                        ],
                        'created_by' => $owner->getKey(),
                        'updated_by' => $owner->getKey(),
                    ]
                );

                $created['ring_groups']++;

                foreach ($groupRow['members'] as $index => $memberRow) {
                    $extension = null;
                    $memberUser = null;

                    if ($memberRow['member_type'] === RingGroupMemberType::Extension->value) {
                        $extension = Extension::query()
                            ->where('tenant_id', $tenant->getKey())
                            ->where('number', $memberRow['extension_number'])
                            ->first();
                    } else {
                        $memberUser = User::query()->where('email', $memberRow['user_email'])->first();
                    }

                    if ($memberRow['member_type'] === RingGroupMemberType::Extension->value && ! $extension instanceof Extension) {
                        continue;
                    }

                    if ($memberRow['member_type'] === RingGroupMemberType::User->value && ! $memberUser instanceof User) {
                        continue;
                    }

                    RingGroupMember::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->getKey(),
                            'ring_group_id' => $ringGroup->getKey(),
                            'member_type' => $memberRow['member_type'],
                            'extension_id' => $extension?->getKey(),
                            'user_id' => $memberUser?->getKey(),
                        ],
                        [
                            'uuid' => $this->stableRingGroupMemberUuid(
                                $tenant,
                                $groupRow['slug'],
                                $memberRow['member_type'].':'.($extension?->number ?? $memberUser?->email ?? (string) $index)
                            ),
                            'priority' => $memberRow['priority'],
                            'delay_seconds' => $memberRow['delay_seconds'],
                            'timeout_seconds' => $memberRow['timeout_seconds'],
                            'is_active' => true,
                            'metadata' => [
                                'inventory_source' => 'demo_seeder',
                            ],
                        ]
                    );

                    $created['ring_group_members']++;
                }
            }
        } finally {
            $this->tenantContext->clear();
        }

        return $created;
    }

    private function stableRingGroupUuid(Tenant $tenant, string $slug): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':ring-group:'.$slug), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function stableRingGroupMemberUuid(Tenant $tenant, string $slug, string $memberKey): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':ring-group-member:'.$slug.':'.$memberKey), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    /**
     * @return array{call_queues:int, call_queue_members:int, queue_member_pauses:int}
     */
    private function seedCallQueues(Tenant $tenant, string $tenantPrefix): array
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $agent = User::query()->where('email', sprintf('%s-agent@test.local', $tenantPrefix))->first();
        $telephony = User::query()->where('email', sprintf('%s-telephony@test.local', $tenantPrefix))->first();

        if (! $owner instanceof User || ! $agent instanceof User || ! $telephony instanceof User) {
            return [
                'call_queues' => 0,
                'call_queue_members' => 0,
                'queue_member_pauses' => 0,
            ];
        }

        $created = [
            'call_queues' => 0,
            'call_queue_members' => 0,
            'queue_member_pauses' => 0,
        ];

        $queues = [
            [
                'slug' => 'support-queue',
                'name' => Str::title($tenantPrefix).' Support Queue',
                'description' => 'Primary support queue for incoming calls.',
                'strategy' => CallQueueStrategy::RingAll->value,
                'status' => CallQueueStatus::Active->value,
                'overflow_destination_type' => 'ring_group',
                'overflow_destination_slug' => 'support-ring-group',
                'members' => [
                    ['member_type' => CallQueueMemberType::User->value, 'user_email' => sprintf('%s-owner@test.local', $tenantPrefix), 'priority' => 1, 'penalty' => 0, 'paused' => false],
                    ['member_type' => CallQueueMemberType::Extension->value, 'extension_number' => '2001', 'priority' => 2, 'penalty' => 1, 'paused' => false],
                ],
            ],
            [
                'slug' => 'sales-queue',
                'name' => Str::title($tenantPrefix).' Sales Queue',
                'description' => 'Sequential sales queue with a paused member for validation.',
                'strategy' => CallQueueStrategy::Sequential->value,
                'status' => CallQueueStatus::Active->value,
                'overflow_destination_type' => 'queue',
                'overflow_destination_slug' => 'support-queue',
                'members' => [
                    ['member_type' => CallQueueMemberType::User->value, 'user_email' => sprintf('%s-agent@test.local', $tenantPrefix), 'priority' => 1, 'penalty' => 0, 'paused' => true, 'pause_reason' => 'Lunch break'],
                    ['member_type' => CallQueueMemberType::Extension->value, 'extension_number' => '2002', 'priority' => 2, 'penalty' => 0, 'paused' => false],
                ],
            ],
            [
                'slug' => 'billing-queue',
                'name' => Str::title($tenantPrefix).' Billing Queue',
                'description' => 'Empty queue used to exercise overflow and no-member states.',
                'strategy' => CallQueueStrategy::Random->value,
                'status' => CallQueueStatus::Suspended->value,
                'overflow_destination_type' => 'user',
                'overflow_destination_user_email' => sprintf('%s-telephony@test.local', $tenantPrefix),
                'members' => [],
            ],
        ];

        $this->tenantContext->setTenant($tenant);

        try {
            foreach ($queues as $queueRow) {
                $overflowDestinationId = null;

                if (($queueRow['overflow_destination_type'] ?? null) === 'ring_group') {
                    $overflowDestinationId = RingGroup::query()
                        ->where('tenant_id', $tenant->getKey())
                        ->where('slug', $queueRow['overflow_destination_slug'])
                        ->value('id');
                } elseif (($queueRow['overflow_destination_type'] ?? null) === 'queue') {
                    $overflowDestinationId = CallQueue::query()
                        ->where('tenant_id', $tenant->getKey())
                        ->where('slug', $queueRow['overflow_destination_slug'])
                        ->value('id');
                } elseif (($queueRow['overflow_destination_type'] ?? null) === 'user') {
                    $overflowDestinationId = User::query()
                        ->where('email', $queueRow['overflow_destination_user_email'])
                        ->value('id');
                }

                $queue = CallQueue::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'slug' => $queueRow['slug'],
                    ],
                    [
                        'uuid' => $this->stableCallQueueUuid($tenant, $queueRow['slug']),
                        'name' => $queueRow['name'],
                        'description' => $queueRow['description'],
                        'strategy' => $queueRow['strategy'],
                        'status' => $queueRow['status'],
                        'max_wait_time_seconds' => 300,
                        'ring_timeout_seconds' => 20,
                        'retry_delay_seconds' => 5,
                        'max_attempts' => 3,
                        'music_on_hold' => 'demo-moh',
                        'announce_position' => true,
                        'announce_estimated_wait' => true,
                        'overflow_destination_type' => $queueRow['overflow_destination_type'],
                        'overflow_destination_id' => $overflowDestinationId,
                        'settings' => [
                            'demo' => true,
                            'inventory_source' => 'demo_seeder',
                        ],
                        'metadata' => [
                            'inventory_source' => 'demo_seeder',
                        ],
                        'created_by' => $owner->getKey(),
                        'updated_by' => $owner->getKey(),
                    ]
                );

                $created['call_queues']++;

                foreach ($queueRow['members'] as $index => $memberRow) {
                    $memberUser = null;
                    $extension = null;

                    if ($memberRow['member_type'] === CallQueueMemberType::User->value) {
                        $memberUser = User::query()->where('email', $memberRow['user_email'])->first();
                    } else {
                        $extension = Extension::query()
                            ->where('tenant_id', $tenant->getKey())
                            ->where('number', $memberRow['extension_number'])
                            ->first();
                    }

                    if ($memberRow['member_type'] === CallQueueMemberType::User->value && ! $memberUser instanceof User) {
                        continue;
                    }

                    if ($memberRow['member_type'] === CallQueueMemberType::Extension->value && ! $extension instanceof Extension) {
                        continue;
                    }

                    $member = CallQueueMember::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->getKey(),
                            'call_queue_id' => $queue->getKey(),
                            'member_type' => $memberRow['member_type'],
                            'member_id' => $memberRow['member_type'] === CallQueueMemberType::Extension->value ? $extension?->getKey() : $memberUser?->getKey(),
                        ],
                        [
                            'uuid' => $this->stableCallQueueMemberUuid(
                                $tenant,
                                $queueRow['slug'],
                                $memberRow['member_type'].':'.($extension?->number ?? $memberUser?->email ?? (string) $index)
                            ),
                            'extension_id' => $extension?->getKey(),
                            'user_id' => $memberUser?->getKey(),
                            'priority' => $memberRow['priority'],
                            'penalty' => $memberRow['penalty'],
                            'is_active' => true,
                            'is_paused' => (bool) ($memberRow['paused'] ?? false),
                            'paused_at' => ! empty($memberRow['paused']) ? now()->subHours(3) : null,
                            'pause_reason' => $memberRow['pause_reason'] ?? null,
                            'metadata' => [
                                'inventory_source' => 'demo_seeder',
                            ],
                        ]
                    );

                    $created['call_queue_members']++;

                    if (! empty($memberRow['paused'])) {
                        QueueMemberPause::query()->updateOrCreate(
                            [
                                'tenant_id' => $tenant->getKey(),
                                'call_queue_id' => $queue->getKey(),
                                'call_queue_member_id' => $member->getKey(),
                                'ended_at' => null,
                            ],
                            [
                                'uuid' => $this->stableQueueMemberPauseUuid($tenant, $queueRow['slug'], $member->uuid),
                                'user_id' => $telephony->getKey(),
                                'started_at' => now()->subHours(3),
                                'reason' => $memberRow['pause_reason'] ?? 'Paused for demo validation.',
                                'metadata' => [
                                    'inventory_source' => 'demo_seeder',
                                ],
                            ]
                        );

                        $created['queue_member_pauses']++;
                    }
                }
            }
        } finally {
            $this->tenantContext->clear();
        }

        return $created;
    }

    private function stableCallQueueUuid(Tenant $tenant, string $slug): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':call-queue:'.$slug), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function stableCallQueueMemberUuid(Tenant $tenant, string $slug, string $memberKey): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':call-queue-member:'.$slug.':'.$memberKey), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function stableIvrMenuUuid(Tenant $tenant, string $slug): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':ivr-menu:'.$slug), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function stableIvrOptionUuid(Tenant $tenant, string $slug, string $digit): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':ivr-option:'.$slug.':'.$digit), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    /**
     * @return array{ivr_menus:int, ivr_options:int}
     */
    private function seedIvrMenus(Tenant $tenant, string $tenantPrefix): array
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $telephony = User::query()->where('email', sprintf('%s-telephony@test.local', $tenantPrefix))->first();
        $supportRingGroup = RingGroup::query()->where('tenant_id', $tenant->getKey())->where('slug', 'support-ring-group')->first();
        $supportQueue = CallQueue::query()->where('tenant_id', $tenant->getKey())->where('slug', 'support-queue')->first();
        $salesQueue = CallQueue::query()->where('tenant_id', $tenant->getKey())->where('slug', 'sales-queue')->first();

        if (! $owner instanceof User || ! $telephony instanceof User || ! $supportRingGroup instanceof RingGroup || ! $supportQueue instanceof CallQueue || ! $salesQueue instanceof CallQueue) {
            return [
                'ivr_menus' => 0,
                'ivr_options' => 0,
            ];
        }

        $created = [
            'ivr_menus' => 0,
            'ivr_options' => 0,
        ];

        // The demo IVR graph is deterministic so tests and portfolio screenshots
        // can rely on stable menu and option identifiers across repeated seeds.
        $menus = [
            [
                'slug' => 'main-business-hours-ivr',
                'name' => Str::title($tenantPrefix).' Main IVR',
                'description' => 'Primary business-hours IVR for incoming callers.',
                'greeting_text' => 'Welcome to '.Str::title($tenantPrefix).'. Press 1 for support or 2 for sales.',
                'repeat_count' => 2,
                'input_timeout_seconds' => 6,
                'max_invalid_attempts' => 3,
                'timeout_action_type' => IvrActionType::Route->value,
                'timeout_destination_type' => IvrDestinationType::RingGroup->value,
                'timeout_destination_id' => $supportRingGroup->getKey(),
                'invalid_action_type' => IvrActionType::Repeat->value,
                'invalid_destination_type' => null,
                'invalid_destination_id' => null,
                'options' => [
                    ['digit' => '1', 'label' => 'Support', 'destination_type' => IvrDestinationType::RingGroup->value, 'destination_id' => $supportRingGroup->getKey(), 'priority' => 1],
                    ['digit' => '2', 'label' => 'Sales', 'destination_type' => IvrDestinationType::CallQueue->value, 'destination_id' => $salesQueue->getKey(), 'priority' => 2],
                    ['digit' => '9', 'label' => 'After hours', 'destination_type' => IvrDestinationType::IvrMenu->value, 'destination_id' => null, 'priority' => 3],
                ],
            ],
            [
                'slug' => 'after-hours-ivr',
                'name' => Str::title($tenantPrefix).' After Hours IVR',
                'description' => 'Secondary IVR for outside business hours.',
                'greeting_text' => 'You have reached '.Str::title($tenantPrefix).'. Press 1 for the support queue.',
                'repeat_count' => 1,
                'input_timeout_seconds' => 5,
                'max_invalid_attempts' => 2,
                'timeout_action_type' => IvrActionType::Hangup->value,
                'timeout_destination_type' => null,
                'timeout_destination_id' => null,
                'invalid_action_type' => IvrActionType::Route->value,
                'invalid_destination_type' => IvrDestinationType::CallQueue->value,
                'invalid_destination_id' => $supportQueue->getKey(),
                'options' => [
                    ['digit' => '1', 'label' => 'Support Queue', 'destination_type' => IvrDestinationType::CallQueue->value, 'destination_id' => $supportQueue->getKey(), 'priority' => 1],
                ],
            ],
        ];

        $this->tenantContext->setTenant($tenant);

        try {
            $menuIdMap = [];

            foreach ($menus as $menuRow) {
                $menu = IvrMenu::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'slug' => $menuRow['slug'],
                    ],
                    [
                        'uuid' => $this->stableIvrMenuUuid($tenant, $menuRow['slug']),
                        'name' => $menuRow['name'],
                        'description' => $menuRow['description'],
                        'status' => IvrMenuStatus::Active->value,
                        'greeting_text' => $menuRow['greeting_text'],
                        'greeting_audio_path' => null,
                        'repeat_count' => $menuRow['repeat_count'],
                        'input_timeout_seconds' => $menuRow['input_timeout_seconds'],
                        'max_invalid_attempts' => $menuRow['max_invalid_attempts'],
                        'timeout_action_type' => $menuRow['timeout_action_type'],
                        'timeout_destination_type' => $menuRow['timeout_destination_type'],
                        'timeout_destination_id' => $menuRow['timeout_destination_id'],
                        'invalid_action_type' => $menuRow['invalid_action_type'],
                        'invalid_destination_type' => $menuRow['invalid_destination_type'],
                        'invalid_destination_id' => $menuRow['invalid_destination_id'],
                        'settings' => [
                            'demo' => true,
                            'inventory_source' => 'demo_seeder',
                        ],
                        'metadata' => [
                            'inventory_source' => 'demo_seeder',
                        ],
                        'created_by' => $owner->getKey(),
                        'updated_by' => $owner->getKey(),
                    ]
                );

                $menuIdMap[$menuRow['slug']] = $menu->getKey();
                $created['ivr_menus']++;
            }

            foreach ($menus as $menuRow) {
                $menu = IvrMenu::query()->where('tenant_id', $tenant->getKey())->where('slug', $menuRow['slug'])->first();
                if (! $menu instanceof IvrMenu) {
                    continue;
                }

                foreach ($menuRow['options'] as $optionRow) {
                    $destinationId = $optionRow['destination_id'];
                    if ($optionRow['destination_type'] === IvrDestinationType::IvrMenu->value) {
                        $destinationId = $menuIdMap['after-hours-ivr'] ?? null;
                    }

                    if (! $destinationId) {
                        continue;
                    }

                    IvrOption::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->getKey(),
                            'ivr_menu_id' => $menu->getKey(),
                            'digit' => $optionRow['digit'],
                        ],
                        [
                            'uuid' => $this->stableIvrOptionUuid($tenant, $menuRow['slug'], $optionRow['digit']),
                            'label' => $optionRow['label'],
                            'destination_type' => $optionRow['destination_type'],
                            'destination_id' => $destinationId,
                            'priority' => $optionRow['priority'],
                            'is_active' => true,
                            'metadata' => [
                                'inventory_source' => 'demo_seeder',
                            ],
                        ]
                    );

                    $created['ivr_options']++;
                }
            }
        } finally {
            $this->tenantContext->clear();
        }

        return $created;
    }

    private function stableQueueMemberPauseUuid(Tenant $tenant, string $slug, string $memberUuid): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':queue-member-pause:'.$slug.':'.$memberUuid), 0, 32);

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

    private function seedCallLogs(Tenant $tenant, string $tenantPrefix): int
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $agent = User::query()->where('email', sprintf('%s-agent@test.local', $tenantPrefix))->first();

        if (! $owner instanceof User) {
            return 0;
        }

        $ownerExtension = Extension::query()->where('tenant_id', $tenant->getKey())->where('number', '2001')->first();
        $agentExtension = Extension::query()->where('tenant_id', $tenant->getKey())->where('number', '2002')->first();
        $ownerDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', '+15550001001')->first();
        $agentDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', $tenantPrefix === 'tenant-a' ? '+15550001003' : '+15550001001')->first();
        $unassignedDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', '+15550001999')->first();
        $contact = Contact::query()->where('tenant_id', $tenant->getKey())->where('display_name', Str::title($tenantPrefix).' Support Contact')->first();
        $archivedVendor = Contact::query()->where('tenant_id', $tenant->getKey())->where('display_name', Str::title($tenantPrefix).' Archived Vendor')->first();

        $rows = $tenantPrefix === 'tenant-a'
            ? [
                [
                    'key' => 'shared-provider-call',
                    'direction' => TelephonyCallDirection::Inbound,
                    'status' => TelephonyCallStatus::Completed,
                    'disposition' => CallDisposition::Answered,
                    'from' => '+15550009999',
                    'to' => '+15550001001',
                    'caller_contact_id' => $contact?->getKey(),
                    'callee_user_id' => $owner->getKey(),
                    'callee_extension_id' => $ownerExtension?->getKey(),
                    'callee_phone_number_id' => $ownerDid?->getKey(),
                    'started_offset' => 6,
                    'ringing' => 8,
                    'talk' => 90,
                    'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                ],
                [
                    'key' => 'tenant-a-missed',
                    'direction' => TelephonyCallDirection::Inbound,
                    'status' => TelephonyCallStatus::Completed,
                    'disposition' => CallDisposition::NoAnswer,
                    'from' => '+15550007777',
                    'to' => '+15550001999',
                    'callee_phone_number_id' => $unassignedDid?->getKey(),
                    'started_offset' => 5,
                    'ringing' => 25,
                    'talk' => 0,
                    'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallCompleted],
                ],
                [
                    'key' => 'tenant-a-outbound-answered',
                    'direction' => TelephonyCallDirection::Outbound,
                    'status' => TelephonyCallStatus::Completed,
                    'disposition' => CallDisposition::Answered,
                    'from' => '+15550001001',
                    'to' => '+15550003333',
                    'caller_user_id' => $owner->getKey(),
                    'caller_extension_id' => $ownerExtension?->getKey(),
                    'caller_phone_number_id' => $ownerDid?->getKey(),
                    'callee_contact_id' => $archivedVendor?->getKey(),
                    'started_offset' => 4,
                    'ringing' => 10,
                    'talk' => 180,
                    'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                ],
                [
                    'key' => 'tenant-a-outbound-failed',
                    'direction' => TelephonyCallDirection::Outbound,
                    'status' => TelephonyCallStatus::Failed,
                    'disposition' => CallDisposition::Failed,
                    'from' => '+15550001003',
                    'to' => '+15550008888',
                    'caller_user_id' => $agent?->getKey(),
                    'caller_extension_id' => $agentExtension?->getKey(),
                    'caller_phone_number_id' => $agentDid?->getKey(),
                    'started_offset' => 3,
                    'ringing' => 0,
                    'talk' => 0,
                    'failure_code' => 'FAKE_DOWN',
                    'failure_message' => 'Synthetic provider failure.',
                    'events' => [CallEventType::CallCreated, CallEventType::CallFailed],
                ],
                [
                    'key' => 'tenant-a-internal',
                    'direction' => TelephonyCallDirection::Internal,
                    'status' => TelephonyCallStatus::Completed,
                    'disposition' => CallDisposition::Answered,
                    'from' => '2001',
                    'to' => '2002',
                    'caller_user_id' => $owner->getKey(),
                    'callee_user_id' => $agent?->getKey(),
                    'caller_extension_id' => $ownerExtension?->getKey(),
                    'callee_extension_id' => $agentExtension?->getKey(),
                    'started_offset' => 2,
                    'ringing' => 2,
                    'talk' => 60,
                    'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                ],
            ]
            : [
                [
                    'key' => 'shared-provider-call',
                    'direction' => TelephonyCallDirection::Inbound,
                    'status' => TelephonyCallStatus::Completed,
                    'disposition' => CallDisposition::Answered,
                    'from' => '+15550009999',
                    'to' => '+15550001001',
                    'caller_contact_id' => $contact?->getKey(),
                    'callee_user_id' => $owner->getKey(),
                    'callee_extension_id' => $ownerExtension?->getKey(),
                    'callee_phone_number_id' => $ownerDid?->getKey(),
                    'started_offset' => 2,
                    'ringing' => 4,
                    'talk' => 45,
                    'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                ],
                [
                    'key' => 'tenant-b-outbound-failed',
                    'direction' => TelephonyCallDirection::Outbound,
                    'status' => TelephonyCallStatus::Failed,
                    'disposition' => CallDisposition::Busy,
                    'from' => '+15550001001',
                    'to' => '+15550006666',
                    'caller_user_id' => $owner->getKey(),
                    'caller_extension_id' => $ownerExtension?->getKey(),
                    'caller_phone_number_id' => $ownerDid?->getKey(),
                    'started_offset' => 1,
                    'ringing' => 0,
                    'talk' => 0,
                    'hangup_cause' => 'busy',
                    'events' => [CallEventType::CallCreated, CallEventType::CallFailed],
                ],
            ];

        foreach ($rows as $index => $row) {
            $startedAt = Carbon::now()->subDays((int) $row['started_offset'])->setTime(10 + $index, 0);
            $ringingAt = $startedAt->copy();
            $answeredAt = $row['talk'] > 0 ? $ringingAt->copy()->addSeconds((int) $row['ringing']) : null;
            $endedAt = $row['talk'] > 0
                ? $answeredAt?->copy()->addSeconds((int) $row['talk'])
                : $ringingAt->copy()->addSeconds(max(5, (int) $row['ringing']));
            $billingStatus = $row['direction'] === TelephonyCallDirection::Internal
                ? CallBillingStatus::NonBillable
                : ($row['status'] === TelephonyCallStatus::Failed ? CallBillingStatus::Failed : CallBillingStatus::Unrated);

            $callLog = CallLog::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'provider_id' => 'fake',
                    'provider_call_id' => (string) $row['key'],
                ],
                [
                    'uuid' => $this->stableCallLogUuid($tenant, (string) $row['key']),
                    'correlation_id' => 'seed-'.$tenantPrefix.'-'.$index,
                    'direction' => $row['direction']->value,
                    'status' => $row['status']->value,
                    'disposition' => $row['disposition']->value,
                    'from_number' => $row['from'],
                    'from_normalized_number' => preg_replace('/\D+/', '', $row['from']) === $row['from'] && ! str_starts_with((string) $row['from'], '+')
                        ? $row['from']
                        : preg_replace('/\s+/', '', $row['from']),
                    'to_number' => $row['to'],
                    'to_normalized_number' => preg_replace('/\D+/', '', $row['to']) === $row['to'] && ! str_starts_with((string) $row['to'], '+')
                        ? $row['to']
                        : preg_replace('/\s+/', '', $row['to']),
                    'caller_user_id' => $row['caller_user_id'] ?? null,
                    'callee_user_id' => $row['callee_user_id'] ?? null,
                    'caller_extension_id' => $row['caller_extension_id'] ?? null,
                    'callee_extension_id' => $row['callee_extension_id'] ?? null,
                    'caller_phone_number_id' => $row['caller_phone_number_id'] ?? null,
                    'callee_phone_number_id' => $row['callee_phone_number_id'] ?? null,
                    'caller_contact_id' => $row['caller_contact_id'] ?? null,
                    'callee_contact_id' => $row['callee_contact_id'] ?? null,
                    'started_at' => $startedAt,
                    'ringing_at' => $ringingAt,
                    'answered_at' => $answeredAt,
                    'ended_at' => $endedAt,
                    'ringing_seconds' => $answeredAt ? max(0, $answeredAt->diffInSeconds($ringingAt)) : 0,
                    'talk_seconds' => $answeredAt ? max(0, $endedAt->diffInSeconds($answeredAt)) : 0,
                    'billable_seconds' => $answeredAt ? max(0, $endedAt->diffInSeconds($answeredAt)) : 0,
                    'total_seconds' => max(0, $endedAt->diffInSeconds($startedAt)),
                    'hangup_cause' => $row['hangup_cause'] ?? null,
                    'failure_code' => $row['failure_code'] ?? null,
                    'failure_message' => $row['failure_message'] ?? null,
                    'billing_status' => $billingStatus->value,
                    'recording_available' => false,
                    'metadata' => ['seeded' => true, 'scenario' => $row['key']],
                ]
            );

            foreach ($row['events'] as $sequence => $eventType) {
                $occurredAt = match ($eventType) {
                    CallEventType::CallCreated => $startedAt,
                    CallEventType::CallRinging => $ringingAt,
                    CallEventType::CallAnswered => $answeredAt ?? $ringingAt,
                    default => $endedAt,
                };

                CallEvent::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'provider_id' => 'fake',
                        'provider_event_id' => sprintf('%s:%s', $row['key'], $eventType->value),
                    ],
                    [
                        'uuid' => $this->stableCallEventUuid($tenant, sprintf('%s:%s', $row['key'], $eventType->value)),
                        'call_log_id' => $callLog->getKey(),
                        'type' => $eventType->value,
                        'occurred_at' => $occurredAt,
                        'sequence' => $sequence + 1,
                        'payload' => [
                            'seeded' => true,
                            'status' => $callLog->status?->value ?? $callLog->status,
                            'disposition' => $callLog->disposition?->value ?? $callLog->disposition,
                        ],
                        'created_at' => $occurredAt,
                    ]
                );
            }
        }

        return count($rows);
    }

    private function seedCallLogVolume(Tenant $tenant, string $tenantPrefix, int $count): int
    {
        $owner = User::query()->where('email', sprintf('%s-owner@test.local', $tenantPrefix))->first();
        $admin = User::query()->where('email', sprintf('%s-admin@test.local', $tenantPrefix))->first();
        $agent = User::query()->where('email', sprintf('%s-agent@test.local', $tenantPrefix))->first();
        $telephonyManager = User::query()->where('email', sprintf('%s-telephony@test.local', $tenantPrefix))->first();
        $analyst = User::query()->where('email', sprintf('%s-analyst@test.local', $tenantPrefix))->first();
        $teamManager = User::query()->where('email', sprintf('%s-team@test.local', $tenantPrefix))->first();
        $readOnly = User::query()->where('email', sprintf('%s-readonly@test.local', $tenantPrefix))->first();
        $supportContact = Contact::query()->where('tenant_id', $tenant->getKey())->where('display_name', Str::title($tenantPrefix).' Support Contact')->first();
        $archivedVendor = Contact::query()->where('tenant_id', $tenant->getKey())->where('display_name', Str::title($tenantPrefix).' Archived Vendor')->first();
        $ownerExtension = Extension::query()->where('tenant_id', $tenant->getKey())->where('number', '2001')->first();
        $agentExtension = Extension::query()->where('tenant_id', $tenant->getKey())->where('number', '2002')->first();
        $ownerDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', '+15550001001')->first();
        $agentDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', $tenantPrefix === 'tenant-a' ? '+15550001003' : '+15550001001')->first();
        $secondaryDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', '+15550001002')->first();
        $sharedDid = PhoneNumber::query()->where('tenant_id', $tenant->getKey())->where('normalized_number', '+15550001999')->first();

        $users = array_values(array_filter([
            $owner,
            $admin,
            $agent,
            $telephonyManager,
            $analyst,
            $teamManager,
            $readOnly,
        ]));

        if ($users === []) {
            return 0;
        }

        $contacts = array_values(array_filter([$supportContact, $archivedVendor]));
        $externalNumbers = $tenantPrefix === 'tenant-a'
            ? ['+15550007001', '+15550007002', '+15550007003', '+15550007004', '+15550007005', '+15550007006']
            : ['+15550008001', '+15550008002', '+15550008003', '+15550008004', '+15550008005', '+15550008006'];

        $scenarios = [
            [
                'call_type' => 'incoming',
                'direction' => TelephonyCallDirection::Inbound,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::Answered,
                'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                'answerable' => true,
            ],
            [
                'call_type' => 'incoming',
                'direction' => TelephonyCallDirection::Inbound,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::NoAnswer,
                'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallCompleted],
                'answerable' => false,
            ],
            [
                'call_type' => 'incoming',
                'direction' => TelephonyCallDirection::Inbound,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::Busy,
                'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallCompleted],
                'answerable' => false,
            ],
            [
                'call_type' => 'declined',
                'direction' => TelephonyCallDirection::Outbound,
                'status' => TelephonyCallStatus::Cancelled,
                'disposition' => CallDisposition::Rejected,
                'events' => [CallEventType::CallCreated, CallEventType::CallInitiated, CallEventType::CallRinging, CallEventType::CallCancelled],
                'answerable' => false,
            ],
            [
                'call_type' => 'outgoing',
                'direction' => TelephonyCallDirection::Outbound,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::Answered,
                'events' => [CallEventType::CallCreated, CallEventType::CallInitiated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                'answerable' => true,
            ],
            [
                'call_type' => 'outgoing',
                'direction' => TelephonyCallDirection::Outbound,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::Busy,
                'events' => [CallEventType::CallCreated, CallEventType::CallInitiated, CallEventType::CallRinging, CallEventType::CallCompleted],
                'answerable' => false,
            ],
            [
                'call_type' => 'failed',
                'direction' => TelephonyCallDirection::Outbound,
                'status' => TelephonyCallStatus::Failed,
                'disposition' => CallDisposition::Failed,
                'events' => [CallEventType::CallCreated, CallEventType::CallInitiated, CallEventType::CallFailed],
                'answerable' => false,
            ],
            [
                'call_type' => 'internal',
                'direction' => TelephonyCallDirection::Internal,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::Answered,
                'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted],
                'answerable' => true,
            ],
            [
                'call_type' => 'conference',
                'direction' => TelephonyCallDirection::Internal,
                'status' => TelephonyCallStatus::Completed,
                'disposition' => CallDisposition::Answered,
                'events' => [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallHeld, CallEventType::CallResumed, CallEventType::CallCompleted],
                'answerable' => true,
            ],
        ];

        $created = 0;

        for ($index = 0; $index < $count; $index++) {
            $scenario = $scenarios[$index % count($scenarios)];
            $recordNumber = $index + 1;
            $recordKey = sprintf('%s-volume-%03d-%s', $tenantPrefix, $recordNumber, $scenario['call_type']);
            $startedAt = Carbon::now()
                ->subDays(30 + (($index * 7 + ($tenantPrefix === 'tenant-a' ? 3 : 11)) % 61))
                ->setTime(8 + ($index % 8), ($index * 13) % 60, ($index * 17) % 60);
            $ringingSeconds = 3 + ($index % 18);
            $talkSeconds = $scenario['answerable']
                ? 35 + (($index * 11) % 240)
                : 0;
            $answeredAt = $scenario['answerable']
                ? $startedAt->copy()->addSeconds($ringingSeconds)
                : null;
            $endedAt = $scenario['answerable']
                ? $answeredAt->copy()->addSeconds($talkSeconds)
                : $startedAt->copy()->addSeconds(max(6, $ringingSeconds));

            $callerUser = $users[$index % count($users)];
            $calleeUser = $users[($index + 2) % count($users)];
            $callerExtension = $ownerExtension ?? $agentExtension;
            $calleeExtension = $agentExtension ?? $ownerExtension;
            $callerDid = $ownerDid ?? $secondaryDid;
            $calleeDid = $agentDid ?? $sharedDid ?? $ownerDid;
            $contact = $contacts[$index % max(1, count($contacts))] ?? null;
            $externalNumber = $externalNumbers[$index % count($externalNumbers)];

            $attributes = [
                'uuid' => $this->stableCallLogUuid($tenant, $recordKey),
                'correlation_id' => sprintf('seed-%s-%03d', $tenantPrefix, $recordNumber),
                'direction' => $scenario['direction']->value,
                'status' => $scenario['status']->value,
                'disposition' => $scenario['disposition']->value,
                'from_number' => '',
                'from_normalized_number' => '',
                'to_number' => '',
                'to_normalized_number' => '',
                'caller_user_id' => null,
                'callee_user_id' => null,
                'caller_extension_id' => null,
                'callee_extension_id' => null,
                'caller_phone_number_id' => null,
                'callee_phone_number_id' => null,
                'caller_contact_id' => null,
                'callee_contact_id' => null,
                'started_at' => $startedAt,
                'ringing_at' => $startedAt,
                'answered_at' => $answeredAt,
                'ended_at' => $endedAt,
                'ringing_seconds' => $scenario['answerable'] ? $ringingSeconds : 0,
                'talk_seconds' => $scenario['answerable'] ? $talkSeconds : 0,
                'billable_seconds' => $scenario['direction'] === TelephonyCallDirection::Internal ? 0 : ($scenario['answerable'] ? $talkSeconds : 0),
                'total_seconds' => max(0, $endedAt->diffInSeconds($startedAt)),
                'hangup_cause' => null,
                'failure_code' => null,
                'failure_message' => null,
                'billing_status' => $scenario['direction'] === TelephonyCallDirection::Internal
                    ? CallBillingStatus::NonBillable->value
                    : ($scenario['status'] === TelephonyCallStatus::Failed ? CallBillingStatus::Failed->value : CallBillingStatus::Unrated->value),
                'recording_available' => false,
                'metadata' => [
                    'seeded' => true,
                    'scenario' => $scenario['call_type'],
                    'call_type' => $scenario['call_type'],
                ],
            ];

            match ($scenario['call_type']) {
                'incoming' => [
                    $attributes['from_number'] = $externalNumber,
                    $attributes['from_normalized_number'] = $externalNumber,
                    $attributes['to_number'] = $ownerDid?->display_number ?? $ownerDid?->number ?? $externalNumber,
                    $attributes['to_normalized_number'] = $ownerDid?->normalized_number ?? $ownerDid?->number ?? $externalNumber,
                    $attributes['callee_user_id'] = $callerUser->getKey(),
                    $attributes['callee_extension_id'] = $ownerExtension?->getKey(),
                    $attributes['callee_phone_number_id'] = $ownerDid?->getKey(),
                    $attributes['caller_contact_id'] = $contact?->getKey(),
                    $attributes['hangup_cause'] = $scenario['disposition'] === CallDisposition::Busy ? 'busy' : null,
                ],
                'declined' => [
                    $attributes['from_number'] = $callerDid?->display_number ?? $callerDid?->number ?? '+15550001001',
                    $attributes['from_normalized_number'] = $callerDid?->normalized_number ?? $callerDid?->number ?? '+15550001001',
                    $attributes['to_number'] = $externalNumber,
                    $attributes['to_normalized_number'] = $externalNumber,
                    $attributes['caller_user_id'] = $callerUser->getKey(),
                    $attributes['caller_extension_id'] = $callerExtension?->getKey(),
                    $attributes['caller_phone_number_id'] = $callerDid?->getKey(),
                    $attributes['callee_contact_id'] = $contact?->getKey(),
                    $attributes['hangup_cause'] = 'declined',
                ],
                'outgoing' => [
                    $attributes['from_number'] = $callerDid?->display_number ?? $callerDid?->number ?? '+15550001001',
                    $attributes['from_normalized_number'] = $callerDid?->normalized_number ?? $callerDid?->number ?? '+15550001001',
                    $attributes['to_number'] = $externalNumber,
                    $attributes['to_normalized_number'] = $externalNumber,
                    $attributes['caller_user_id'] = $callerUser->getKey(),
                    $attributes['caller_extension_id'] = $callerExtension?->getKey(),
                    $attributes['caller_phone_number_id'] = $callerDid?->getKey(),
                    $attributes['callee_contact_id'] = $contact?->getKey(),
                    $attributes['hangup_cause'] = $scenario['status'] === TelephonyCallStatus::Failed ? 'unreachable' : ($scenario['disposition'] === CallDisposition::Busy ? 'busy' : null),
                ],
                'failed' => [
                    $attributes['from_number'] = $callerDid?->display_number ?? $callerDid?->number ?? '+15550001001',
                    $attributes['from_normalized_number'] = $callerDid?->normalized_number ?? $callerDid?->number ?? '+15550001001',
                    $attributes['to_number'] = $externalNumber,
                    $attributes['to_normalized_number'] = $externalNumber,
                    $attributes['caller_user_id'] = $callerUser->getKey(),
                    $attributes['caller_extension_id'] = $callerExtension?->getKey(),
                    $attributes['caller_phone_number_id'] = $callerDid?->getKey(),
                    $attributes['failure_code'] = 'FAKE_DOWN',
                    $attributes['failure_message'] = 'Synthetic provider failure.',
                    $attributes['hangup_cause'] = 'network_error',
                ],
                'internal' => [
                    $attributes['from_number'] = $callerExtension?->number ?? '2001',
                    $attributes['from_normalized_number'] = $callerExtension?->number ?? '2001',
                    $attributes['to_number'] = $calleeExtension?->number ?? '2002',
                    $attributes['to_normalized_number'] = $calleeExtension?->number ?? '2002',
                    $attributes['caller_user_id'] = $callerUser->getKey(),
                    $attributes['callee_user_id'] = $calleeUser->getKey(),
                    $attributes['caller_extension_id'] = $callerExtension?->getKey(),
                    $attributes['callee_extension_id'] = $calleeExtension?->getKey(),
                    $attributes['caller_phone_number_id'] = $callerDid?->getKey(),
                    $attributes['callee_phone_number_id'] = $calleeDid?->getKey(),
                ],
                'conference' => [
                    $attributes['from_number'] = $callerExtension?->number ?? '2001',
                    $attributes['from_normalized_number'] = $callerExtension?->number ?? '2001',
                    $attributes['to_number'] = 'conf-'.substr($recordKey, -6),
                    $attributes['to_normalized_number'] = 'conf-'.substr($recordKey, -6),
                    $attributes['caller_user_id'] = $callerUser->getKey(),
                    $attributes['callee_user_id'] = $calleeUser->getKey(),
                    $attributes['caller_extension_id'] = $callerExtension?->getKey(),
                    $attributes['callee_extension_id'] = $calleeExtension?->getKey(),
                    $attributes['caller_phone_number_id'] = $callerDid?->getKey(),
                    $attributes['callee_phone_number_id'] = $calleeDid?->getKey(),
                    $attributes['metadata'] = [
                        'seeded' => true,
                        'scenario' => 'conference',
                        'call_type' => 'conference',
                        'participant_count' => 3,
                    ],
                ],
                default => [],
            };

            $callLog = CallLog::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'provider_id' => 'fake',
                    'provider_call_id' => $recordKey,
                ],
                array_merge($attributes, [
                    'uuid' => $this->stableCallLogUuid($tenant, $recordKey),
                ])
            );

            CallLog::query()
                ->whereKey($callLog->getKey())
                ->update([
                    'created_at' => $startedAt,
                    'updated_at' => $endedAt,
                ]);

            foreach ($scenario['events'] as $sequence => $eventType) {
                $occurredAt = match ($eventType) {
                    CallEventType::CallCreated => $startedAt,
                    CallEventType::CallInitiated => $startedAt->copy()->addSeconds(1),
                    CallEventType::CallRinging => $startedAt->copy()->addSeconds($ringingSeconds),
                    CallEventType::CallAnswered => $answeredAt ?? $startedAt->copy()->addSeconds($ringingSeconds),
                    CallEventType::CallHeld => $answeredAt ? $answeredAt->copy()->addSeconds(15) : $startedAt->copy()->addSeconds($ringingSeconds + 15),
                    CallEventType::CallResumed => $answeredAt ? $answeredAt->copy()->addSeconds(35) : $startedAt->copy()->addSeconds($ringingSeconds + 35),
                    CallEventType::CallCompleted => $endedAt,
                    CallEventType::CallFailed => $endedAt,
                    CallEventType::CallCancelled => $endedAt,
                };

                CallEvent::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'provider_id' => 'fake',
                        'provider_event_id' => sprintf('%s:%s', $recordKey, $eventType->value),
                    ],
                    [
                        'uuid' => $this->stableCallEventUuid($tenant, sprintf('%s:%s', $recordKey, $eventType->value)),
                        'call_log_id' => $callLog->getKey(),
                        'type' => $eventType->value,
                        'occurred_at' => $occurredAt,
                        'sequence' => $sequence + 1,
                        'payload' => [
                            'seeded' => true,
                            'scenario' => $scenario['call_type'],
                            'status' => $callLog->status?->value ?? $callLog->status,
                            'disposition' => $callLog->disposition?->value ?? $callLog->disposition,
                            'hangup_cause' => $callLog->hangup_cause,
                        ],
                        'created_at' => $occurredAt,
                    ]
                );
            }

            $created++;
        }

        return $created;
    }

    private function stableCallLogUuid(Tenant $tenant, string $key): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':call-log:'.$key), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function stableCallEventUuid(Tenant $tenant, string $key): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':call-event:'.$key), 0, 32);

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
