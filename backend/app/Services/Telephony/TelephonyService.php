<?php

namespace App\Services\Telephony;

use App\DTO\Telephony\TelephonyCallOptions;
use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyCallResult;
use App\DTO\Telephony\TelephonyCallState;
use App\DTO\Telephony\TelephonyConferenceInput;
use App\DTO\Telephony\TelephonyConferenceResult;
use App\DTO\Telephony\TelephonyEndpointInput;
use App\DTO\Telephony\TelephonyEndpointResult;
use App\DTO\Telephony\TelephonyParticipantResult;
use App\DTO\Telephony\TelephonyProviderDescriptor;
use App\DTO\Telephony\TelephonyProviderHealth;
use App\DTO\Telephony\TelephonyTransferRequest;
use App\Enums\Telephony\TelephonyCapability;
use App\Exceptions\Telephony\TelephonyProviderUnavailableException;
use App\Services\CallLogs\CallRecordingService;
use App\Services\Monitoring\StructuredLogContextService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelephonyService
{
    public function __construct(
        private readonly TelephonyProviderRegistry $registry,
        private readonly TenantContext $tenantContext,
        private readonly StructuredLogContextService $structuredLogs,
        private readonly CallRecordingService $callRecordingService,
    ) {
    }

    /**
     * @return array<int, TelephonyProviderDescriptor>
     */
    public function providers(): array
    {
        $this->requireTenantId();

        return array_map(
            static fn ($provider): TelephonyProviderDescriptor => $provider->descriptor(),
            $this->registry->providers()
        );
    }

    public function defaultProviderDescriptor(): TelephonyProviderDescriptor
    {
        $this->requireTenantId();

        return $this->registry->defaultProvider()->descriptor();
    }

    public function defaultProviderHealth(): TelephonyProviderHealth
    {
        $this->requireTenantId();

        return $this->registry->defaultProvider()->health();
    }

    public function createEndpoint(TelephonyEndpointInput $input): TelephonyEndpointResult
    {
        return $this->record('create_endpoint', $input->correlationId, function () use ($input): TelephonyEndpointResult {
            return $this->registry->endpointProvisioningProvider()->createEndpoint(
                $this->normalizeEndpointInput($input)
            );
        });
    }

    public function updateEndpoint(string $endpointKey, TelephonyEndpointInput $input): TelephonyEndpointResult
    {
        return $this->record('update_endpoint', $input->correlationId, function () use ($endpointKey, $input): TelephonyEndpointResult {
            return $this->registry->endpointProvisioningProvider()->updateEndpoint(
                $endpointKey,
                $this->normalizeEndpointInput($input)
            );
        });
    }

    public function suspendEndpoint(string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        $tenantId = $this->requireTenantId();

        return $this->record('suspend_endpoint', $correlationId, function () use ($tenantId, $endpointKey, $correlationId): TelephonyEndpointResult {
            return $this->registry->endpointProvisioningProvider()->suspendEndpoint($tenantId, $endpointKey, $correlationId);
        });
    }

    public function activateEndpoint(string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        $tenantId = $this->requireTenantId();

        return $this->record('activate_endpoint', $correlationId, function () use ($tenantId, $endpointKey, $correlationId): TelephonyEndpointResult {
            return $this->registry->endpointProvisioningProvider()->activateEndpoint($tenantId, $endpointKey, $correlationId);
        });
    }

    public function deleteEndpoint(string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        $tenantId = $this->requireTenantId();

        return $this->record('delete_endpoint', $correlationId, function () use ($tenantId, $endpointKey, $correlationId): TelephonyEndpointResult {
            return $this->registry->endpointProvisioningProvider()->deleteEndpoint($tenantId, $endpointKey, $correlationId);
        });
    }

    public function fetchEndpointState(string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        $tenantId = $this->requireTenantId();

        return $this->record('fetch_endpoint_state', $correlationId, function () use ($tenantId, $endpointKey, $correlationId): TelephonyEndpointResult {
            return $this->registry->endpointProvisioningProvider()->fetchEndpointState($tenantId, $endpointKey, $correlationId);
        });
    }

    public function originateCall(
        TelephonyCallParty $from,
        TelephonyCallParty $to,
        TelephonyCallOptions $options
    ): TelephonyCallResult {
        return $this->record('originate_call', $options->correlationId, function () use ($from, $to, $options): TelephonyCallResult {
            $result = $this->registry->callControlProvider()->originateCall(
                $from,
                $to,
                $this->normalizeCallOptions($options)
            );

            $this->callRecordingService->recordOriginatedCall(
                $result,
                $this->registry->defaultProvider()->providerId(),
            );

            return $result;
        });
    }

    public function answerCall(string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $tenantId = $this->requireTenantId();

        return $this->record('answer_call', $correlationId, function () use ($tenantId, $callId, $correlationId): TelephonyCallState {
            $state = $this->registry->callControlProvider()->answerCall($tenantId, $callId, $correlationId);
            $this->callRecordingService->recordStateTransition($state, $this->registry->defaultProvider()->providerId(), \App\Enums\CallLogs\CallEventType::CallAnswered);

            return $state;
        });
    }

    public function holdCall(string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $tenantId = $this->requireTenantId();

        return $this->record('hold_call', $correlationId, function () use ($tenantId, $callId, $correlationId): TelephonyCallState {
            $state = $this->registry->callControlProvider()->holdCall($tenantId, $callId, $correlationId);
            $this->callRecordingService->recordStateTransition($state, $this->registry->defaultProvider()->providerId(), \App\Enums\CallLogs\CallEventType::CallHeld);

            return $state;
        });
    }

    public function resumeCall(string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $tenantId = $this->requireTenantId();

        return $this->record('resume_call', $correlationId, function () use ($tenantId, $callId, $correlationId): TelephonyCallState {
            $state = $this->registry->callControlProvider()->resumeCall($tenantId, $callId, $correlationId);
            $this->callRecordingService->recordStateTransition($state, $this->registry->defaultProvider()->providerId(), \App\Enums\CallLogs\CallEventType::CallResumed);

            return $state;
        });
    }

    public function hangupCall(string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $tenantId = $this->requireTenantId();

        return $this->record('hangup_call', $correlationId, function () use ($tenantId, $callId, $correlationId): TelephonyCallState {
            $state = $this->registry->callControlProvider()->hangupCall($tenantId, $callId, $correlationId);
            $this->callRecordingService->recordStateTransition(
                $state,
                $this->registry->defaultProvider()->providerId(),
                \App\Enums\CallLogs\CallEventType::CallCompleted,
                ['disposition' => 'answered']
            );

            return $state;
        });
    }

    public function transferCall(TelephonyTransferRequest $request): TelephonyCallState
    {
        return $this->record('transfer_call', $request->correlationId, function () use ($request): TelephonyCallState {
            return $this->registry->callControlProvider()->transferCall(
                $this->normalizeTransferRequest($request)
            );
        });
    }

    public function muteCall(string $callId, bool $muted, ?string $correlationId = null): TelephonyCallState
    {
        $tenantId = $this->requireTenantId();

        return $this->record('mute_call', $correlationId, function () use ($tenantId, $callId, $muted, $correlationId): TelephonyCallState {
            return $this->registry->callControlProvider()->muteCall($tenantId, $callId, $muted, $correlationId);
        });
    }

    public function fetchActiveCallState(string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $tenantId = $this->requireTenantId();

        return $this->record('fetch_call_state', $correlationId, function () use ($tenantId, $callId, $correlationId): TelephonyCallState {
            return $this->registry->callControlProvider()->fetchActiveCallState($tenantId, $callId, $correlationId);
        });
    }

    public function createConference(TelephonyConferenceInput $input): TelephonyConferenceResult
    {
        return $this->record('create_conference', $input->correlationId, function () use ($input): TelephonyConferenceResult {
            return $this->registry->conferenceControlProvider()->createConference(
                $this->normalizeConferenceInput($input)
            );
        });
    }

    public function addConferenceParticipant(
        string $conferenceId,
        TelephonyCallParty $party,
        ?string $correlationId = null
    ): TelephonyParticipantResult {
        $tenantId = $this->requireTenantId();

        return $this->record('add_conference_participant', $correlationId, function () use ($tenantId, $conferenceId, $party, $correlationId): TelephonyParticipantResult {
            return $this->registry->conferenceControlProvider()->addParticipant($tenantId, $conferenceId, $party, $correlationId);
        });
    }

    public function destroyConference(string $conferenceId, ?string $correlationId = null): void
    {
        $tenantId = $this->requireTenantId();

        $this->record('destroy_conference', $correlationId, function () use ($tenantId, $conferenceId, $correlationId): bool {
            $this->registry->conferenceControlProvider()->destroyConference($tenantId, $conferenceId, $correlationId);

            return true;
        });
    }

    /**
     * @return array<int, TelephonyParticipantResult>
     */
    public function listConferenceParticipants(string $conferenceId, ?string $correlationId = null): array
    {
        $tenantId = $this->requireTenantId();

        return $this->record('list_conference_participants', $correlationId, function () use ($tenantId, $conferenceId, $correlationId): array {
            return $this->registry->conferenceControlProvider()->listParticipants($tenantId, $conferenceId, $correlationId);
        });
    }

    public function providerSupports(TelephonyCapability $capability): bool
    {
        return in_array($capability, $this->registry->defaultProvider()->capabilities(), true);
    }

    private function normalizeEndpointInput(TelephonyEndpointInput $input): TelephonyEndpointInput
    {
        return new TelephonyEndpointInput(
            tenantId: $this->requireTenantId(),
            endpointKey: $input->endpointKey,
            address: $input->address,
            displayName: $input->displayName,
            desiredStatus: $input->desiredStatus,
            correlationId: $input->correlationId ?? $this->newCorrelationId(),
            idempotencyKey: $input->idempotencyKey,
            metadata: $input->metadata,
        );
    }

    private function normalizeCallOptions(TelephonyCallOptions $options): TelephonyCallOptions
    {
        return new TelephonyCallOptions(
            tenantId: $this->requireTenantId(),
            direction: $options->direction,
            correlationId: $options->correlationId ?? $this->newCorrelationId(),
            idempotencyKey: $options->idempotencyKey,
            metadata: $options->metadata,
        );
    }

    private function normalizeTransferRequest(TelephonyTransferRequest $request): TelephonyTransferRequest
    {
        return new TelephonyTransferRequest(
            tenantId: $this->requireTenantId(),
            callId: $request->callId,
            target: $request->target,
            type: $request->type,
            correlationId: $request->correlationId ?? $this->newCorrelationId(),
            metadata: $request->metadata,
        );
    }

    private function normalizeConferenceInput(TelephonyConferenceInput $input): TelephonyConferenceInput
    {
        return new TelephonyConferenceInput(
            tenantId: $this->requireTenantId(),
            conferenceKey: $input->conferenceKey,
            displayName: $input->displayName,
            correlationId: $input->correlationId ?? $this->newCorrelationId(),
            idempotencyKey: $input->idempotencyKey,
            metadata: $input->metadata,
        );
    }

    private function requireTenantId(): string
    {
        $tenantId = $this->tenantContext->tenantId();
        if (! is_string($tenantId) || $tenantId === '') {
            throw new TelephonyProviderUnavailableException('Tenant context is required for telephony operations.');
        }

        return $tenantId;
    }

    private function newCorrelationId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function record(string $operation, ?string $correlationId, callable $callback): mixed
    {
        $startedAt = microtime(true);
        $providerId = config('telephony.default_provider', 'fake');
        $effectiveCorrelationId = $correlationId ?? $this->newCorrelationId();

        try {
            $result = $callback();

            Log::info('telephony.operation.completed', $this->structuredLogs->sanitize([
                'module' => 'telephony',
                'provider_id' => $providerId,
                'operation' => $operation,
                'correlation_id' => $effectiveCorrelationId,
                'tenant_id' => $this->tenantContext->tenantId(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'result' => 'success',
            ]));

            return $result;
        } catch (\Throwable $exception) {
            Log::warning('telephony.operation.failed', $this->structuredLogs->sanitize([
                'module' => 'telephony',
                'provider_id' => $providerId,
                'operation' => $operation,
                'correlation_id' => $effectiveCorrelationId,
                'tenant_id' => $this->tenantContext->tenantId(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'result' => 'failed',
                ...$this->structuredLogs->summarizeThrowable($exception),
            ]));

            throw $exception;
        }
    }
}
