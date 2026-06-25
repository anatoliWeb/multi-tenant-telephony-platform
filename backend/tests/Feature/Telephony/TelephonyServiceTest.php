<?php

namespace Tests\Feature\Telephony;

use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyConferenceInput;
use App\Enums\Telephony\TelephonyCapability;
use App\Exceptions\Telephony\TelephonyProviderUnavailableException;
use App\Services\Telephony\TelephonyService;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Telephony\Concerns\InteractsWithTelephony;
use Tests\TestCase;

class TelephonyServiceTest extends TestCase
{
    use InteractsWithTelephony;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'fake');
        $this->resetFakeTelephony();
        Log::spy();
    }

    public function test_service_requires_tenant_context_and_resolves_from_container(): void
    {
        $this->assertInstanceOf(TelephonyService::class, app(TelephonyService::class));

        $this->expectException(TelephonyProviderUnavailableException::class);
        app(TelephonyService::class)->defaultProviderDescriptor();
    }

    public function test_service_uses_tenant_context_and_keeps_fake_state_isolated(): void
    {
        $service = app(TelephonyService::class);

        $this->setTelephonyTenant('tenant-a');
        $endpointA = $service->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'forged-tenant',
            'endpointKey' => 'shared-endpoint',
            'idempotencyKey' => 'endpoint-key',
        ]));

        $this->setTelephonyTenant('tenant-b', 'tenant-b');
        $endpointB = $service->createEndpoint($this->fakeEndpointInput([
            'tenantId' => 'forged-tenant',
            'endpointKey' => 'shared-endpoint',
            'idempotencyKey' => 'endpoint-key',
        ]));

        $this->assertSame('tenant-a', $endpointA->tenantId);
        $this->assertSame('tenant-b', $endpointB->tenantId);
        $this->assertNotSame($endpointA->providerResourceId, $endpointB->providerResourceId);
    }

    public function test_service_supports_call_and_conference_flows(): void
    {
        $this->setTelephonyTenant('tenant-a');
        $service = app(TelephonyService::class);

        $call = $service->originateCall(
            new TelephonyCallParty(identifier: 'user-a', displayName: 'User A', number: '+10000001'),
            new TelephonyCallParty(identifier: 'user-b', displayName: 'User B', number: '+10000002'),
            $this->fakeCallOptions(['idempotencyKey' => 'call-key'])
        );

        $this->assertSame('tenant-a', $call->tenantId);
        $this->assertTrue($service->providerSupports(TelephonyCapability::ConferenceControl));

        $conference = $service->createConference(new TelephonyConferenceInput(
            tenantId: 'spoofed-tenant',
            conferenceKey: 'daily-standup',
            displayName: 'Daily Standup',
            idempotencyKey: 'conf-key',
        ));

        $participant = $service->addConferenceParticipant(
            $conference->conferenceId,
            new TelephonyCallParty(identifier: 'user-a', displayName: 'User A')
        );

        $this->assertSame('tenant-a', $conference->tenantId);
        $this->assertSame('user-a', $participant->participantKey);
    }

    public function test_service_does_not_reuse_cleared_tenant_context(): void
    {
        $service = app(TelephonyService::class);

        $this->setTelephonyTenant('tenant-a');
        $service->createEndpoint($this->fakeEndpointInput([
            'endpointKey' => 'endpoint-a',
        ]));

        $this->clearTelephonyTenant();

        $this->expectException(TelephonyProviderUnavailableException::class);
        $service->createEndpoint($this->fakeEndpointInput([
            'endpointKey' => 'endpoint-b',
        ]));
    }
}
