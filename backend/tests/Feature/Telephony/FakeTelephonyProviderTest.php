<?php

namespace Tests\Feature\Telephony;

use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyConferenceInput;
use App\DTO\Telephony\TelephonyTransferRequest;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Enums\Telephony\TelephonyCapability;
use App\Enums\Telephony\TelephonyEndpointStatus;
use App\Enums\Telephony\TelephonyFailureCode;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Exceptions\Telephony\TelephonyOperationFailedException;
use App\Exceptions\Telephony\TelephonyUnsupportedCapabilityException;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\Telephony\Concerns\InteractsWithTelephony;
use Tests\TestCase;

class FakeTelephonyProviderTest extends TestCase
{
    use InteractsWithTelephony;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'fake');
        $this->resetFakeTelephony();
    }

    public function test_fake_provider_exposes_identity_capabilities_and_health(): void
    {
        $provider = $this->resetFakeTelephony();

        $this->assertSame('fake', $provider->providerId());
        $this->assertSame('Fake Telephony Provider', $provider->displayName());
        $this->assertContains(TelephonyCapability::CallOrigination, $provider->capabilities());
        $this->assertSame('healthy', $provider->health()->status->value);
    }

    public function test_endpoint_lifecycle_is_tenant_scoped(): void
    {
        $provider = $this->resetFakeTelephony();
        $created = $provider->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'tenant-a',
            'idempotencyKey' => 'endpoint-a',
        ]));

        $this->assertSame(TelephonyEndpointStatus::Active, $created->status);

        $suspended = $provider->suspendEndpoint('tenant-a', $created->endpointKey, 'corr-a');
        $this->assertSame(TelephonyEndpointStatus::Suspended, $suspended->status);

        $activated = $provider->activateEndpoint('tenant-a', $created->endpointKey, 'corr-a');
        $this->assertSame(TelephonyEndpointStatus::Active, $activated->status);

        $deleted = $provider->deleteEndpoint('tenant-a', $created->endpointKey, 'corr-a');
        $this->assertSame(TelephonyEndpointStatus::Deleted, $deleted->status);
    }

    public function test_call_lifecycle_and_transfer_are_supported(): void
    {
        $provider = $this->resetFakeTelephony();

        $call = $provider->originateCall(
            new TelephonyCallParty(identifier: 'user-a', displayName: 'User A', number: '+10000001'),
            new TelephonyCallParty(identifier: 'user-b', displayName: 'User B', number: '+10000002'),
            $this->fakeCallOptions([
                'tenantId' => 'tenant-a',
                'idempotencyKey' => 'call-a',
            ]),
        );

        $this->assertSame(TelephonyCallStatus::Ringing, $call->status);

        $answered = $provider->answerCall('tenant-a', $call->callId, 'corr-call');
        $this->assertSame(TelephonyCallStatus::Answered, $answered->status);

        $held = $provider->holdCall('tenant-a', $call->callId, 'corr-call');
        $this->assertSame(TelephonyCallStatus::Held, $held->status);

        $resumed = $provider->resumeCall('tenant-a', $call->callId, 'corr-call');
        $this->assertSame(TelephonyCallStatus::Answered, $resumed->status);

        $transferred = $provider->transferCall(new TelephonyTransferRequest(
            tenantId: 'tenant-a',
            callId: $call->callId,
            target: new TelephonyCallParty(identifier: 'user-c', displayName: 'User C', number: '+10000003'),
            correlationId: 'corr-transfer',
        ));
        $this->assertSame('user-c', $transferred->to->identifier);

        $muted = $provider->muteCall('tenant-a', $call->callId, true, 'corr-call');
        $this->assertTrue($muted->muted);

        $completed = $provider->hangupCall('tenant-a', $call->callId, 'corr-call');
        $this->assertSame(TelephonyCallStatus::Completed, $completed->status);
    }

    public function test_conference_lifecycle_is_supported(): void
    {
        $provider = $this->resetFakeTelephony();

        $conference = $provider->createConference(new TelephonyConferenceInput(
            tenantId: 'tenant-a',
            conferenceKey: 'support-room',
            displayName: 'Support Room',
            correlationId: 'conf-1',
            idempotencyKey: 'conf-1',
        ));

        $participant = $provider->addParticipant(
            'tenant-a',
            $conference->conferenceId,
            new TelephonyCallParty(identifier: 'agent-1', displayName: 'Agent 1')
        );

        $this->assertSame('agent-1', $participant->participantKey);
        $this->assertCount(1, $provider->listParticipants('tenant-a', $conference->conferenceId));

        $muted = $provider->muteParticipant('tenant-a', $conference->conferenceId, 'agent-1', true);
        $this->assertTrue($muted->muted);

        $provider->removeParticipant('tenant-a', $conference->conferenceId, 'agent-1');
        $this->assertCount(0, $provider->listParticipants('tenant-a', $conference->conferenceId));
    }

    public function test_unsupported_capabilities_fail_predictably(): void
    {
        config()->set('telephony.providers.fake.capabilities', [
            TelephonyCapability::EndpointProvisioning->value,
        ]);

        $provider = $this->resetFakeTelephony();

        $this->expectException(TelephonyUnsupportedCapabilityException::class);
        $provider->originateCall(
            new TelephonyCallParty(identifier: 'user-a'),
            new TelephonyCallParty(identifier: 'user-b'),
            $this->fakeCallOptions(),
        );
    }

    public function test_deterministic_failures_can_be_injected(): void
    {
        config()->set('telephony.providers.fake.failures.originate_call', [
            'failure_code' => TelephonyFailureCode::OperationFailed->value,
            'message' => 'Fake originate failure.',
            'provider_code' => 'FAKE_DOWN',
        ]);

        $provider = $this->resetFakeTelephony();

        $this->expectException(TelephonyOperationFailedException::class);
        $provider->originateCall(
            new TelephonyCallParty(identifier: 'user-a'),
            new TelephonyCallParty(identifier: 'user-b'),
            $this->fakeCallOptions(),
        );
    }

    public function test_idempotency_is_tenant_scoped_and_conflict_safe(): void
    {
        $provider = $this->resetFakeTelephony();

        $first = $provider->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'tenant-a',
            'endpointKey' => 'shared-endpoint',
            'displayName' => 'Tenant A endpoint',
            'idempotencyKey' => 'same-key',
        ]));

        $second = $provider->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'tenant-a',
            'endpointKey' => 'shared-endpoint',
            'displayName' => 'Tenant A endpoint',
            'idempotencyKey' => 'same-key',
        ]));

        $otherTenant = $provider->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'tenant-b',
            'endpointKey' => 'shared-endpoint',
            'displayName' => 'Tenant B endpoint',
            'idempotencyKey' => 'same-key',
        ]));

        $this->assertSame($first->providerResourceId, $second->providerResourceId);
        $this->assertNotSame($first->providerResourceId, $otherTenant->providerResourceId);

        $this->expectException(TelephonyConflictException::class);
        $provider->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'tenant-a',
            'endpointKey' => 'shared-endpoint',
            'displayName' => 'Changed payload',
            'idempotencyKey' => 'same-key',
        ]));
    }
}
