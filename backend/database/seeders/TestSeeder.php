<?php

namespace Database\Seeders;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\CallLogs\CallEventType;
use App\Enums\TenantMembershipStatus;
use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\CallEvent;
use App\Models\CallLog;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\ContactTag;
use App\Models\Extension;
use App\Models\ExtensionCredential;
use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Database\Seeders\Support\RbacSeedService;
use Database\Seeders\Support\SeederEnvironmentService;
use Database\Seeders\Support\TenantSeedService;
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
        $tenants = app(TenantSeedService::class)->ensureBaseTenants();
        $tenantRoles = [];

        foreach ($tenants as $tenant) {
            $tenantRoles[$tenant->id] = $rbacSeed->seedTenantRoles($tenant);
            $rbacSeed->syncTenantRolePermissions($tenant, $tenantRoles[$tenant->id], $permissions);
        }

        $rbacSeed->syncPermissions($platformRoles['platform_super_admin'], $permissions['platform']);
        $rbacSeed->syncPermissions($platformRoles['platform_support'], $permissions['platform_support']);
        $rbacSeed->syncPermissions($platformRoles['admin'], $permissions['platform_admin']);
        $rbacSeed->syncPermissions($platformRoles['manager'], ['users.view', 'users.edit']);
        $rbacSeed->syncPermissions($platformRoles['user'], ['users.view']);
        $rbacSeed->invalidateRbacCaches();

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

        $rbacSeed->assignPlatformRoles($platformAdmin, [
            $platformRoles['platform_super_admin'],
            $platformRoles['admin'],
        ]);

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

        $this->seedContacts($defaultTenant, $tenantOwner, '+15558880001');
        $this->seedContacts($secondaryTenant, $tenantAdmin, '+15558880001');
        $this->seedExtension($defaultTenant, $tenantOwner, '2001');
        $this->seedExtension($secondaryTenant, $tenantAdmin, '2001');
        $this->seedPhoneNumber($defaultTenant, $tenantOwner, '+15550001001', true, '+1 555 000 1001');
        $this->seedPhoneNumber($secondaryTenant, $tenantAdmin, '+15550001001', true, '+1 555 000 1001');
        $this->seedCallLog($defaultTenant, $tenantOwner, 'fixture-call-a', [
            'direction' => TelephonyCallDirection::Inbound,
            'status' => TelephonyCallStatus::Completed,
            'disposition' => CallDisposition::Answered,
            'from_number' => '+15558880001',
            'to_number' => '+15550001001',
        ]);
        $this->seedCallLog($secondaryTenant, $tenantAdmin, 'fixture-call-a', [
            'direction' => TelephonyCallDirection::Outbound,
            'status' => TelephonyCallStatus::Failed,
            'disposition' => CallDisposition::Busy,
            'from_number' => '+15550001001',
            'to_number' => '+15558880001',
        ]);
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

    protected function seedContacts(Tenant $tenant, User $owner, string $sharedPhone): void
    {
        $tag = ContactTag::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'slug' => 'test-tag',
            ],
            [
                'uuid' => $this->stableUuid($tenant, 'test-tag'),
                'name' => 'Test Tag',
            ]
        );

        $contact = Contact::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'display_name' => 'Fixture Contact',
            ],
            [
                'uuid' => $this->stableUuid($tenant, 'fixture-contact'),
                'first_name' => 'Fixture',
                'last_name' => 'Contact',
                'company_name' => 'Fixture Corp',
                'status' => 'active',
                'created_by' => $owner->getKey(),
                'updated_by' => $owner->getKey(),
            ]
        );

        $contact->tags()->sync([$tag->getKey()]);

        ContactPhone::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'contact_id' => $contact->getKey(),
                'normalized_number' => $sharedPhone,
            ],
            [
                'uuid' => $this->stableUuid($tenant, 'fixture-phone'),
                'label' => 'work',
                'raw_number' => $sharedPhone,
                'extension' => null,
                'is_primary' => true,
                'is_sms_capable' => true,
                'is_active' => true,
            ]
        );
    }

    protected function stableUuid(Tenant $tenant, string $key): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':'.$key), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    protected function seedExtension(Tenant $tenant, User $owner, string $number): void
    {
        $extension = Extension::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'number' => $number,
            ],
            [
                'uuid' => $this->stableUuid($tenant, 'extension-'.$number),
                'label' => 'Fixture Extension',
                'status' => 'active',
                'provisioning_status' => 'provisioned',
                'registration_status' => 'unregistered',
                'assigned_user_id' => $owner->getKey(),
                'endpoint_key' => 'extension:'.$this->stableUuid($tenant, 'endpoint-'.$number),
                'provider_name' => 'fake',
                'provider_resource_id' => 'endpoint-'.$number,
                'credential_username' => $number,
                'last_provisioned_at' => now(),
                'created_by' => $owner->getKey(),
                'updated_by' => $owner->getKey(),
                'metadata' => [
                    'provider_state' => [
                        'provider' => 'fake',
                        'endpoint_status' => 'active',
                        'registration_status' => 'unregistered',
                        'address' => 'sip:'.$number.'@tenant.invalid',
                        'updated_at' => now()->toISOString(),
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
                'username' => $number,
                'secret_encrypted' => encrypt('fixture-secret-'.$number),
                'secret_hint' => substr($number, -4),
                'version' => 1,
                'rotated_by' => $owner->getKey(),
                'rotated_at' => now(),
            ]
        );
    }

    protected function seedPhoneNumber(
        Tenant $tenant,
        User $owner,
        string $number,
        bool $isPrimary = true,
        ?string $displayNumber = null
    ): void {
        PhoneNumber::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'normalized_number' => $number,
            ],
            [
                'uuid' => $this->stableUuid($tenant, 'phone-number-'.$number),
                'number' => $number,
                'display_number' => $displayNumber ?? $number,
                'type' => PhoneNumberType::Did->value,
                'status' => PhoneNumberStatus::Active->value,
                'assignment_status' => PhoneNumberAssignmentStatus::Assigned->value,
                'assigned_user_id' => $owner->getKey(),
                'is_primary' => $isPrimary,
                'primary_assignment_key' => $isPrimary ? $tenant->getKey().'#'.$owner->getKey() : null,
                'provider_name' => 'manual',
                'provider_reference' => 'fixture-'.substr($number, -4),
                'country_code' => '1',
                'capabilities' => ['voice'],
                'metadata' => ['fixture' => true],
                'purchased_at' => now()->subDays(2),
                'activated_at' => now()->subDay(),
                'released_at' => null,
                'created_by' => $owner->getKey(),
                'updated_by' => $owner->getKey(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function seedCallLog(Tenant $tenant, User $owner, string $providerCallId, array $overrides = []): void
    {
        $startedAt = now()->subHours(2);
        $answeredAt = ($overrides['status'] ?? TelephonyCallStatus::Completed) === TelephonyCallStatus::Completed
            ? $startedAt->copy()->addSeconds(5)
            : null;
        $endedAt = $answeredAt?->copy()->addSeconds(65) ?? $startedAt->copy()->addSeconds(10);
        $direction = $overrides['direction'] ?? TelephonyCallDirection::Inbound;
        $status = $overrides['status'] ?? TelephonyCallStatus::Completed;
        $disposition = $overrides['disposition'] ?? CallDisposition::Answered;

        $callLog = CallLog::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'provider_id' => 'fake',
                'provider_call_id' => $providerCallId,
            ],
            [
                'uuid' => $this->stableUuid($tenant, 'call-log-'.$providerCallId),
                'correlation_id' => 'fixture-'.$providerCallId,
                'direction' => $direction->value,
                'status' => $status->value,
                'disposition' => $disposition->value,
                'from_number' => $overrides['from_number'] ?? '+15558880001',
                'from_normalized_number' => $overrides['from_number'] ?? '+15558880001',
                'to_number' => $overrides['to_number'] ?? '+15550001001',
                'to_normalized_number' => $overrides['to_number'] ?? '+15550001001',
                'caller_user_id' => $direction === TelephonyCallDirection::Outbound ? $owner->getKey() : null,
                'callee_user_id' => $direction === TelephonyCallDirection::Inbound ? $owner->getKey() : null,
                'started_at' => $startedAt,
                'ringing_at' => $startedAt,
                'answered_at' => $answeredAt,
                'ended_at' => $endedAt,
                'ringing_seconds' => $answeredAt ? 5 : 0,
                'talk_seconds' => $answeredAt ? 65 : 0,
                'billable_seconds' => $answeredAt ? 65 : 0,
                'total_seconds' => max(0, $endedAt->diffInSeconds($startedAt)),
                'billing_status' => $direction === TelephonyCallDirection::Internal
                    ? CallBillingStatus::NonBillable->value
                    : ($status === TelephonyCallStatus::Failed ? CallBillingStatus::Failed->value : CallBillingStatus::Unrated->value),
                'recording_available' => false,
                'metadata' => ['fixture' => true],
            ]
        );

        $events = $status === TelephonyCallStatus::Failed
            ? [CallEventType::CallCreated, CallEventType::CallFailed]
            : [CallEventType::CallCreated, CallEventType::CallRinging, CallEventType::CallAnswered, CallEventType::CallCompleted];

        foreach ($events as $sequence => $eventType) {
            CallEvent::updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'provider_id' => 'fake',
                    'provider_event_id' => $providerCallId.':'.$eventType->value,
                ],
                [
                    'uuid' => $this->stableUuid($tenant, 'call-event-'.$providerCallId.'-'.$eventType->value),
                    'call_log_id' => $callLog->getKey(),
                    'type' => $eventType->value,
                    'occurred_at' => match ($eventType) {
                        CallEventType::CallCreated => $startedAt,
                        CallEventType::CallRinging => $startedAt,
                        CallEventType::CallAnswered => $answeredAt ?? $startedAt,
                        default => $endedAt,
                    },
                    'sequence' => $sequence + 1,
                    'payload' => ['fixture' => true],
                    'created_at' => now(),
                ]
            );
        }
    }
}
