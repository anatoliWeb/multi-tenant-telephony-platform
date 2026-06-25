<?php

namespace App\Services\Telephony;

use App\Contracts\Telephony\CallControlProvider;
use App\Contracts\Telephony\ConferenceControlProvider;
use App\Contracts\Telephony\EndpointProvisioningProvider;
use App\Contracts\Telephony\TelephonyHealthProvider;
use App\Contracts\Telephony\TelephonyProvider;
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
use App\Enums\Telephony\TelephonyCallStatus;
use App\Enums\Telephony\TelephonyCapability;
use App\Enums\Telephony\TelephonyEndpointStatus;
use App\Enums\Telephony\TelephonyFailureCode;
use App\Enums\Telephony\TelephonyProviderStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Exceptions\Telephony\TelephonyOperationFailedException;
use App\Exceptions\Telephony\TelephonyResourceNotFoundException;
use App\Exceptions\Telephony\TelephonyUnsupportedCapabilityException;
use App\Exceptions\Telephony\TelephonyValidationException;
use Illuminate\Support\Str;

class FakeTelephonyProvider implements TelephonyProvider, EndpointProvisioningProvider, CallControlProvider, ConferenceControlProvider, TelephonyHealthProvider
{
    /**
     * @var array<string, array<string, TelephonyEndpointResult>>
     */
    private array $endpoints = [];

    /**
     * @var array<string, array<string, TelephonyCallState>>
     */
    private array $calls = [];

    /**
     * @var array<string, array<string, TelephonyConferenceResult>>
     */
    private array $conferences = [];

    /**
     * @var array<string, array<string, array<string, TelephonyParticipantResult>>>
     */
    private array $conferenceParticipants = [];

    /**
     * @var array<string, array<string, array<string, array{hash: string, result: mixed}>>>
     */
    private array $idempotency = [];

    /**
     * @var array<string, int>
     */
    private array $sequence = [];

    public function providerId(): string
    {
        return 'fake';
    }

    public function displayName(): string
    {
        return (string) config('telephony.providers.fake.display_name', 'Fake Telephony Provider');
    }

    /**
     * @return array<int, TelephonyCapability>
     */
    public function capabilities(): array
    {
        $configured = (array) config('telephony.providers.fake.capabilities', []);

        return array_values(array_map(
            static fn (string $capability): TelephonyCapability => TelephonyCapability::from($capability),
            $configured
        ));
    }

    public function version(): ?string
    {
        return (string) config('telephony.providers.fake.version', 'fake-1.0');
    }

    public function descriptor(): TelephonyProviderDescriptor
    {
        return new TelephonyProviderDescriptor(
            providerId: $this->providerId(),
            displayName: $this->displayName(),
            capabilities: $this->capabilities(),
            version: $this->version(),
        );
    }

    public function health(): TelephonyProviderHealth
    {
        $status = TelephonyProviderStatus::from(
            (string) config('telephony.providers.fake.health.status', TelephonyProviderStatus::Healthy->value)
        );

        return new TelephonyProviderHealth(
            providerId: $this->providerId(),
            status: $status,
            latencyMs: (int) config('telephony.providers.fake.health.latency_ms', 5),
            degradedReasons: (array) config('telephony.providers.fake.health.degraded_reasons', []),
            checkedAt: now()->toISOString(),
        );
    }

    public function createEndpoint(TelephonyEndpointInput $input): TelephonyEndpointResult
    {
        $this->assertCapability(TelephonyCapability::EndpointProvisioning, $input->correlationId);
        $this->assertTenant($input->tenantId, $input->correlationId);
        $this->maybeFail('create_endpoint', $input->correlationId);

        return $this->rememberIdempotent(
            tenantId: $input->tenantId,
            operation: 'create_endpoint',
            idempotencyKey: $input->idempotencyKey,
            payload: $input->toArray(),
            callback: function () use ($input): TelephonyEndpointResult {
                $now = now()->toISOString();
                $result = new TelephonyEndpointResult(
                    tenantId: $input->tenantId,
                    endpointKey: $input->endpointKey,
                    providerResourceId: $this->nextId('endpoint'),
                    address: $input->address,
                    displayName: $input->displayName,
                    status: $input->desiredStatus,
                    correlationId: $input->correlationId,
                    idempotencyKey: $input->idempotencyKey,
                    metadata: $input->metadata,
                    createdAt: $now,
                    updatedAt: $now,
                );

                $this->endpoints[$input->tenantId][$input->endpointKey] = $result;

                return $result;
            },
            correlationId: $input->correlationId,
        );
    }

    public function updateEndpoint(string $endpointKey, TelephonyEndpointInput $input): TelephonyEndpointResult
    {
        $this->assertCapability(TelephonyCapability::EndpointProvisioning, $input->correlationId);
        $existing = $this->fetchEndpointState($input->tenantId, $endpointKey, $input->correlationId);

        $updated = new TelephonyEndpointResult(
            tenantId: $input->tenantId,
            endpointKey: $existing->endpointKey,
            providerResourceId: $existing->providerResourceId,
            address: $input->address,
            displayName: $input->displayName,
            status: $input->desiredStatus,
            correlationId: $input->correlationId,
            idempotencyKey: $input->idempotencyKey,
            metadata: $input->metadata,
            createdAt: $existing->createdAt,
            updatedAt: now()->toISOString(),
        );

        $this->endpoints[$input->tenantId][$endpointKey] = $updated;

        return $updated;
    }

    public function suspendEndpoint(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        return $this->transitionEndpoint($tenantId, $endpointKey, TelephonyEndpointStatus::Suspended, $correlationId);
    }

    public function activateEndpoint(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        return $this->transitionEndpoint($tenantId, $endpointKey, TelephonyEndpointStatus::Active, $correlationId);
    }

    public function deleteEndpoint(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        return $this->transitionEndpoint($tenantId, $endpointKey, TelephonyEndpointStatus::Deleted, $correlationId);
    }

    public function fetchEndpointState(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult
    {
        $this->assertTenant($tenantId, $correlationId);
        $endpoint = $this->endpoints[$tenantId][$endpointKey] ?? null;

        if (! $endpoint instanceof TelephonyEndpointResult) {
            throw new TelephonyResourceNotFoundException('Telephony endpoint was not found.', null, $correlationId);
        }

        return $endpoint;
    }

    public function originateCall(
        TelephonyCallParty $from,
        TelephonyCallParty $to,
        TelephonyCallOptions $options
    ): TelephonyCallResult {
        $this->assertCapability(TelephonyCapability::CallOrigination, $options->correlationId);
        $this->assertTenant($options->tenantId, $options->correlationId);
        $this->maybeFail('originate_call', $options->correlationId);

        return $this->rememberIdempotent(
            tenantId: $options->tenantId,
            operation: 'originate_call',
            idempotencyKey: $options->idempotencyKey,
            payload: [
                'from' => $from->toArray(),
                'to' => $to->toArray(),
                'options' => $options->toArray(),
            ],
            callback: function () use ($from, $to, $options): TelephonyCallResult {
                $now = now()->toISOString();
                $callId = $this->nextId('call');
                $providerCallId = 'provider-'.$callId;

                $result = new TelephonyCallResult(
                    tenantId: $options->tenantId,
                    callId: $callId,
                    providerCallId: $providerCallId,
                    status: TelephonyCallStatus::Ringing,
                    direction: $options->direction,
                    from: $from,
                    to: $to,
                    correlationId: $options->correlationId,
                    idempotencyKey: $options->idempotencyKey,
                    metadata: $options->metadata,
                    createdAt: $now,
                );

                $this->calls[$options->tenantId][$callId] = new TelephonyCallState(
                    tenantId: $options->tenantId,
                    callId: $callId,
                    providerCallId: $providerCallId,
                    status: TelephonyCallStatus::Ringing,
                    direction: $options->direction,
                    from: $from,
                    to: $to,
                    correlationId: $options->correlationId,
                    metadata: $options->metadata,
                    updatedAt: $now,
                );

                return $result;
            },
            correlationId: $options->correlationId,
        );
    }

    public function answerCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $this->assertCapability(TelephonyCapability::CallAnswer, $correlationId);

        return $this->transitionCall($tenantId, $callId, TelephonyCallStatus::Answered, null, $correlationId);
    }

    public function hangupCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $this->assertCapability(TelephonyCapability::CallHangup, $correlationId);

        return $this->transitionCall($tenantId, $callId, TelephonyCallStatus::Completed, null, $correlationId);
    }

    public function holdCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $this->assertCapability(TelephonyCapability::CallHold, $correlationId);

        return $this->transitionCall($tenantId, $callId, TelephonyCallStatus::Held, null, $correlationId);
    }

    public function resumeCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $this->assertCapability(TelephonyCapability::CallResume, $correlationId);

        return $this->transitionCall($tenantId, $callId, TelephonyCallStatus::Answered, null, $correlationId);
    }

    public function transferCall(TelephonyTransferRequest $request): TelephonyCallState
    {
        $this->assertCapability(TelephonyCapability::CallTransfer, $request->correlationId);
        $state = $this->fetchActiveCallState($request->tenantId, $request->callId, $request->correlationId);

        return $this->replaceCallState(
            $request->tenantId,
            $request->callId,
            new TelephonyCallState(
                tenantId: $state->tenantId,
                callId: $state->callId,
                providerCallId: $state->providerCallId,
                status: $state->status,
                direction: $state->direction,
                from: $state->from,
                to: $request->target,
                muted: $state->muted,
                correlationId: $request->correlationId,
                metadata: array_merge($state->metadata, [
                    'transfer_type' => $request->type->value,
                ]),
                updatedAt: now()->toISOString(),
            )
        );
    }

    public function muteCall(string $tenantId, string $callId, bool $muted, ?string $correlationId = null): TelephonyCallState
    {
        $this->assertCapability(TelephonyCapability::CallMute, $correlationId);
        $state = $this->fetchActiveCallState($tenantId, $callId, $correlationId);

        return $this->replaceCallState(
            $tenantId,
            $callId,
            new TelephonyCallState(
                tenantId: $state->tenantId,
                callId: $state->callId,
                providerCallId: $state->providerCallId,
                status: $state->status,
                direction: $state->direction,
                from: $state->from,
                to: $state->to,
                muted: $muted,
                correlationId: $correlationId,
                metadata: $state->metadata,
                updatedAt: now()->toISOString(),
            )
        );
    }

    public function fetchActiveCallState(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState
    {
        $this->assertTenant($tenantId, $correlationId);
        $state = $this->calls[$tenantId][$callId] ?? null;

        if (! $state instanceof TelephonyCallState) {
            throw new TelephonyResourceNotFoundException('Telephony call was not found.', null, $correlationId);
        }

        return $state;
    }

    public function createConference(TelephonyConferenceInput $input): TelephonyConferenceResult
    {
        $this->assertCapability(TelephonyCapability::ConferenceControl, $input->correlationId);
        $this->assertTenant($input->tenantId, $input->correlationId);

        return $this->rememberIdempotent(
            tenantId: $input->tenantId,
            operation: 'create_conference',
            idempotencyKey: $input->idempotencyKey,
            payload: $input->toArray(),
            callback: function () use ($input): TelephonyConferenceResult {
                $result = new TelephonyConferenceResult(
                    tenantId: $input->tenantId,
                    conferenceId: $this->nextId('conference'),
                    providerConferenceId: $this->nextId('provider-conference'),
                    conferenceKey: $input->conferenceKey,
                    displayName: $input->displayName,
                    participantCount: 0,
                    correlationId: $input->correlationId,
                    idempotencyKey: $input->idempotencyKey,
                    metadata: $input->metadata,
                    createdAt: now()->toISOString(),
                );

                $this->conferences[$input->tenantId][$result->conferenceId] = $result;
                $this->conferenceParticipants[$input->tenantId][$result->conferenceId] = [];

                return $result;
            },
            correlationId: $input->correlationId,
        );
    }

    public function destroyConference(string $tenantId, string $conferenceId, ?string $correlationId = null): void
    {
        $this->assertCapability(TelephonyCapability::ConferenceControl, $correlationId);
        $this->assertConferenceExists($tenantId, $conferenceId, $correlationId);
        unset($this->conferences[$tenantId][$conferenceId], $this->conferenceParticipants[$tenantId][$conferenceId]);
    }

    public function addParticipant(
        string $tenantId,
        string $conferenceId,
        TelephonyCallParty $party,
        ?string $correlationId = null
    ): TelephonyParticipantResult {
        $this->assertCapability(TelephonyCapability::ConferenceControl, $correlationId);
        $this->assertConferenceExists($tenantId, $conferenceId, $correlationId);

        $participant = new TelephonyParticipantResult(
            tenantId: $tenantId,
            conferenceId: $conferenceId,
            participantKey: $party->identifier,
            displayName: $party->displayName ?? $party->identifier,
            muted: false,
            joinedAt: now()->toISOString(),
            metadata: [
                'endpoint_key' => $party->endpointKey,
            ],
        );

        $this->conferenceParticipants[$tenantId][$conferenceId][$participant->participantKey] = $participant;
        $this->recountConference($tenantId, $conferenceId);

        return $participant;
    }

    public function removeParticipant(
        string $tenantId,
        string $conferenceId,
        string $participantKey,
        ?string $correlationId = null
    ): void {
        $this->assertCapability(TelephonyCapability::ConferenceControl, $correlationId);
        $this->assertConferenceExists($tenantId, $conferenceId, $correlationId);
        unset($this->conferenceParticipants[$tenantId][$conferenceId][$participantKey]);
        $this->recountConference($tenantId, $conferenceId);
    }

    public function muteParticipant(
        string $tenantId,
        string $conferenceId,
        string $participantKey,
        bool $muted,
        ?string $correlationId = null
    ): TelephonyParticipantResult {
        $this->assertCapability(TelephonyCapability::ConferenceControl, $correlationId);
        $participant = $this->conferenceParticipants[$tenantId][$conferenceId][$participantKey] ?? null;

        if (! $participant instanceof TelephonyParticipantResult) {
            throw new TelephonyResourceNotFoundException('Telephony conference participant was not found.', null, $correlationId);
        }

        $updated = new TelephonyParticipantResult(
            tenantId: $participant->tenantId,
            conferenceId: $participant->conferenceId,
            participantKey: $participant->participantKey,
            displayName: $participant->displayName,
            muted: $muted,
            joinedAt: $participant->joinedAt,
            metadata: $participant->metadata,
        );

        $this->conferenceParticipants[$tenantId][$conferenceId][$participantKey] = $updated;

        return $updated;
    }

    /**
     * @return array<int, TelephonyParticipantResult>
     */
    public function listParticipants(string $tenantId, string $conferenceId, ?string $correlationId = null): array
    {
        $this->assertCapability(TelephonyCapability::ConferenceControl, $correlationId);
        $this->assertConferenceExists($tenantId, $conferenceId, $correlationId);

        return array_values($this->conferenceParticipants[$tenantId][$conferenceId] ?? []);
    }

    public function resetState(): void
    {
        $this->endpoints = [];
        $this->calls = [];
        $this->conferences = [];
        $this->conferenceParticipants = [];
        $this->idempotency = [];
        $this->sequence = [];
    }

    private function transitionEndpoint(
        string $tenantId,
        string $endpointKey,
        TelephonyEndpointStatus $status,
        ?string $correlationId
    ): TelephonyEndpointResult {
        $existing = $this->fetchEndpointState($tenantId, $endpointKey, $correlationId);

        $updated = new TelephonyEndpointResult(
            tenantId: $existing->tenantId,
            endpointKey: $existing->endpointKey,
            providerResourceId: $existing->providerResourceId,
            address: $existing->address,
            displayName: $existing->displayName,
            status: $status,
            correlationId: $correlationId,
            idempotencyKey: $existing->idempotencyKey,
            metadata: $existing->metadata,
            createdAt: $existing->createdAt,
            updatedAt: now()->toISOString(),
        );

        $this->endpoints[$tenantId][$endpointKey] = $updated;

        return $updated;
    }

    private function transitionCall(
        string $tenantId,
        string $callId,
        TelephonyCallStatus $status,
        ?bool $muted,
        ?string $correlationId
    ): TelephonyCallState {
        $state = $this->fetchActiveCallState($tenantId, $callId, $correlationId);

        return $this->replaceCallState(
            $tenantId,
            $callId,
            new TelephonyCallState(
                tenantId: $state->tenantId,
                callId: $state->callId,
                providerCallId: $state->providerCallId,
                status: $status,
                direction: $state->direction,
                from: $state->from,
                to: $state->to,
                muted: $muted ?? $state->muted,
                correlationId: $correlationId,
                metadata: $state->metadata,
                updatedAt: now()->toISOString(),
            )
        );
    }

    private function replaceCallState(string $tenantId, string $callId, TelephonyCallState $state): TelephonyCallState
    {
        $this->calls[$tenantId][$callId] = $state;

        return $state;
    }

    private function assertConferenceExists(string $tenantId, string $conferenceId, ?string $correlationId): void
    {
        $this->assertTenant($tenantId, $correlationId);

        if (! isset($this->conferences[$tenantId][$conferenceId])) {
            throw new TelephonyResourceNotFoundException('Telephony conference was not found.', null, $correlationId);
        }
    }

    private function recountConference(string $tenantId, string $conferenceId): void
    {
        $conference = $this->conferences[$tenantId][$conferenceId];
        $this->conferences[$tenantId][$conferenceId] = new TelephonyConferenceResult(
            tenantId: $conference->tenantId,
            conferenceId: $conference->conferenceId,
            providerConferenceId: $conference->providerConferenceId,
            conferenceKey: $conference->conferenceKey,
            displayName: $conference->displayName,
            participantCount: count($this->conferenceParticipants[$tenantId][$conferenceId] ?? []),
            correlationId: $conference->correlationId,
            idempotencyKey: $conference->idempotencyKey,
            metadata: $conference->metadata,
            createdAt: $conference->createdAt,
        );
    }

    private function assertCapability(TelephonyCapability $capability, ?string $correlationId): void
    {
        if (! in_array($capability, $this->capabilities(), true)) {
            throw new TelephonyUnsupportedCapabilityException(
                sprintf('Telephony capability [%s] is not supported by the selected provider.', $capability->value),
                null,
                $correlationId
            );
        }
    }

    private function assertTenant(string $tenantId, ?string $correlationId): void
    {
        if ($tenantId === '') {
            throw new TelephonyValidationException('Tenant context is required for telephony operations.', null, $correlationId);
        }
    }

    private function maybeFail(string $operation, ?string $correlationId): void
    {
        $failure = config("telephony.providers.fake.failures.{$operation}");
        if (! is_array($failure)) {
            return;
        }

        $message = (string) ($failure['message'] ?? 'Configured fake telephony failure.');
        $providerCode = isset($failure['provider_code']) ? (string) $failure['provider_code'] : null;
        $failureCode = isset($failure['failure_code'])
            ? TelephonyFailureCode::from((string) $failure['failure_code'])
            : TelephonyFailureCode::OperationFailed;

        throw match ($failureCode) {
            TelephonyFailureCode::Conflict => new TelephonyConflictException($message, $providerCode, $correlationId),
            TelephonyFailureCode::ResourceNotFound => new TelephonyResourceNotFoundException($message, $providerCode, $correlationId),
            TelephonyFailureCode::UnsupportedCapability => new TelephonyUnsupportedCapabilityException($message, $providerCode, $correlationId),
            TelephonyFailureCode::ValidationFailed => new TelephonyValidationException($message, $providerCode, $correlationId),
            default => new TelephonyOperationFailedException($message, $providerCode, $correlationId),
        };
    }

    /**
     * @template T
     * @param array<string, mixed> $payload
     * @param callable(): T $callback
     * @return T
     */
    private function rememberIdempotent(
        string $tenantId,
        string $operation,
        ?string $idempotencyKey,
        array $payload,
        callable $callback,
        ?string $correlationId
    ): mixed {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $callback();
        }

        $hash = hash('sha256', json_encode($this->stablePayload($payload), JSON_THROW_ON_ERROR));
        $existing = $this->idempotency[$tenantId][$operation][$idempotencyKey] ?? null;

        if (is_array($existing)) {
            if (($existing['hash'] ?? null) !== $hash) {
                throw new TelephonyConflictException(
                    'Idempotency key already exists for a different telephony payload.',
                    null,
                    $correlationId
                );
            }

            return $existing['result'];
        }

        $result = $callback();
        $this->idempotency[$tenantId][$operation][$idempotencyKey] = [
            'hash' => $hash,
            'result' => $result,
        ];

        return $result;
    }

    private function nextId(string $prefix): string
    {
        $this->sequence[$prefix] = ($this->sequence[$prefix] ?? 0) + 1;

        return sprintf('%s-%04d', $prefix, $this->sequence[$prefix]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function stablePayload(array $payload): array
    {
        $stable = [];

        foreach ($payload as $key => $value) {
            if ($key === 'correlation_id') {
                continue;
            }

            if (is_array($value)) {
                $stable[$key] = $this->stablePayload($value);
                continue;
            }

            $stable[$key] = $value;
        }

        return $stable;
    }
}
