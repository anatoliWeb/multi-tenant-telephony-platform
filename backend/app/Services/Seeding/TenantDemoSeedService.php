<?php

namespace App\Services\Seeding;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\CallLogs\CallEventType;
use App\Enums\Contacts\ContactStatus;
use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Enums\TenantMembershipStatus;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\CallEvent;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Extension;
use App\Models\ExtensionCredential;
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
        $counts['phone_numbers'] += $this->seedPhoneNumbers($defaultTenant, 'tenant-a');
        $counts['phone_numbers'] += $this->seedPhoneNumbers($secondaryTenant, 'tenant-b');
        $counts['call_logs'] += $this->seedCallLogs($defaultTenant, 'tenant-a');
        $counts['call_logs'] += $this->seedCallLogs($secondaryTenant, 'tenant-b');

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
