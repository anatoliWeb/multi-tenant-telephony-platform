<?php

namespace Tests\Feature\CallLogs;

use App\DTO\Telephony\TelephonyCallParty;
use App\Enums\CallLogs\CallEventType;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\TenantMembershipStatus;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\CallLog;
use App\Services\CallLogs\CallEventService;
use App\Services\Telephony\TelephonyService;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\CallLogs\Concerns\BuildsCallLogFixtures;
use Tests\Feature\Telephony\Concerns\InteractsWithTelephony;
use Tests\TestCase;

class CallLogApiTest extends TestCase
{
    use BuildsCallLogFixtures;
    use DatabaseTransactions;
    use InteractsWithTelephony;

    public function test_call_logs_are_tenant_scoped_and_visibility_respects_own_vs_all_permissions(): void
    {
        $tenantA = $this->createTenant('call-a');
        $tenantB = $this->createTenant('call-b');
        $owner = $this->actingAsTenantUser($this->createUser('call-owner'));
        $agent = $this->createUser('call-agent');
        $manager = $this->createUser('call-manager');

        $this->createMembership($tenantA, $owner);
        $this->createMembership($tenantA, $agent);
        $this->createMembership($tenantA, $manager);
        $this->createMembership($tenantB, $owner);
        $this->assignTenantPermissions($owner, $tenantA, ['call_logs.view', 'call_logs.view_own', 'call_logs.view_statistics']);
        $this->assignTenantPermissions($manager, $tenantA, ['call_logs.view', 'call_logs.view_all', 'call_logs.view_statistics']);
        $this->assignTenantPermissions($owner, $tenantB, ['call_logs.view', 'call_logs.view_own']);

        $ownCall = $this->createCallLogFixture($tenantA, $owner, [
            'provider_call_id' => 'own-call',
            'caller_user_id' => $owner->id,
            'direction' => TelephonyCallDirection::Outbound,
            'status' => TelephonyCallStatus::Completed,
            'disposition' => CallDisposition::Answered,
            'talk_seconds' => 50,
            'billable_seconds' => 50,
            'total_seconds' => 55,
        ]);
        $this->createCallEventFixture($ownCall, CallEventType::CallCompleted, ['disposition' => 'answered'], 'own-call:completed');

        $otherCall = $this->createCallLogFixture($tenantA, $agent, [
            'provider_call_id' => 'other-call',
            'caller_user_id' => $agent->id,
            'direction' => TelephonyCallDirection::Inbound,
            'status' => TelephonyCallStatus::Completed,
            'disposition' => CallDisposition::NoAnswer,
            'answered_at' => null,
            'talk_seconds' => 0,
            'billable_seconds' => 0,
            'total_seconds' => 25,
        ]);
        $this->createCallEventFixture($otherCall, CallEventType::CallCompleted, ['disposition' => 'no_answer'], 'other-call:completed');

        $foreignCall = $this->createCallLogFixture($tenantB, $owner, [
            'provider_call_id' => 'foreign-call',
        ]);

        $this->getJson('/api/v1/call-logs', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.provider_call_id', 'own-call');

        $this->getJson('/api/v1/call-logs/statistics', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.total_calls', 1)
            ->assertJsonPath('data.answered_calls', 1);

        $this->actingAs($manager, 'sanctum');

        $this->getJson('/api/v1/call-logs', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/call-logs/filter-options', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(3, 'data.users');

        $this->getJson("/api/v1/call-logs/{$ownCall->id}/events", ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/call-logs/{$foreignCall->id}", ['X-Tenant-ID' => $tenantA->id])
            ->assertNotFound();
    }

    public function test_event_idempotency_is_safe_and_conflicting_duplicates_fail(): void
    {
        $tenant = $this->createTenant('call-events');
        $owner = $this->createUser('call-events-owner');
        $callLog = $this->createCallLogFixture($tenant, $owner, [
            'provider_call_id' => 'event-call',
        ]);

        $service = app(CallEventService::class);
        $occurredAt = CarbonImmutable::parse('2026-06-26T06:00:00Z');
        $first = $service->append(
            $callLog,
            'fake',
            'provider-event-1',
            CallEventType::CallAnswered,
            ['status' => 'answered'],
            $occurredAt
        );

        $second = $service->append(
            $callLog->refresh(),
            'fake',
            'provider-event-1',
            CallEventType::CallAnswered,
            ['status' => 'answered'],
            $occurredAt
        );

        $this->assertSame($first->id, $second->id);

        $this->expectException(TelephonyConflictException::class);
        $service->append(
            $callLog->refresh(),
            'fake',
            'provider-event-1',
            CallEventType::CallAnswered,
            ['status' => 'held'],
            $occurredAt
        );
    }

    public function test_fake_telephony_operations_record_call_logs_and_events(): void
    {
        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'fake');

        $tenant = $this->createTenant('call-telephony');
        $owner = $this->createUser('call-telephony-owner');
        $contact = $this->createContactFixture($tenant, $owner, [
            'display_name' => 'Customer A',
        ]);
        $this->addContactPhone($contact, '+15550009999');
        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '2001',
            'endpoint_key' => 'extension:owner',
        ]);
        $did = $this->createPhoneNumberFixture($tenant, $owner, [
            'number' => '+15550001001',
            'normalized_number' => '+15550001001',
            'display_number' => '+1 555 000 1001',
            'is_primary' => true,
        ]);

        app(TenantContext::class)->setTenant($tenant);
        $this->resetFakeTelephony();

        $service = app(TelephonyService::class);
        $call = $service->originateCall(
            new TelephonyCallParty(identifier: 'owner', displayName: 'Owner', number: $did->number, endpointKey: $extension->endpoint_key),
            new TelephonyCallParty(identifier: 'customer', displayName: $contact->display_name, number: '+15550009999'),
            $this->fakeCallOptions([
                'tenantId' => $tenant->id,
                'direction' => TelephonyCallDirection::Outbound,
                'idempotencyKey' => 'call-recording',
            ]),
        );

        $service->answerCall($call->callId, 'corr-answer');
        $service->hangupCall($call->callId, 'corr-complete');

        $recorded = CallLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider_call_id', $call->providerCallId)
            ->firstOrFail();

        $this->assertSame($owner->id, $recorded->caller_user_id);
        $this->assertSame($extension->id, $recorded->caller_extension_id);
        $this->assertSame($did->id, $recorded->caller_phone_number_id);
        $this->assertSame($contact->id, $recorded->callee_contact_id);
        $this->assertSame(TelephonyCallStatus::Completed, $recorded->status);
        $this->assertGreaterThanOrEqual(3, $recorded->events()->count());
    }

    public function test_platform_permission_alone_does_not_bypass_tenant_call_log_access(): void
    {
        $tenant = $this->createTenant('call-platform');
        $user = $this->actingAsTenantUser($this->createUser('call-platform-user'));
        $callLog = $this->createCallLogFixture($tenant, $user);

        $this->createMembership($tenant, $user, TenantMembershipStatus::Suspended);
        $this->assignPlatformPermissions($user, ['call_logs.view', 'call_logs.view_all']);

        $this->getJson("/api/v1/call-logs/{$callLog->id}", ['X-Tenant-ID' => $tenant->id])
            ->assertForbidden();
    }
}
