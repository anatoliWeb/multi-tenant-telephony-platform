<?php

namespace App\Services\CallLogs;

use App\DTO\Telephony\TelephonyCallResult;
use App\DTO\Telephony\TelephonyCallState;
use App\Enums\CallLogs\CallEventType;
use App\Enums\CallLogs\CallDisposition;
use App\Models\CallLog;
use Carbon\CarbonImmutable;

class CallRecordingService
{
    public function __construct(
        private readonly CallLogService $callLogService,
        private readonly CallEventService $callEventService,
    ) {
    }

    public function recordOriginatedCall(TelephonyCallResult $result, string $providerId): CallLog
    {
        $callLog = $this->callLogService->createFromProviderCall($result, $providerId);
        $occurredAt = $result->createdAt ? CarbonImmutable::parse($result->createdAt) : CarbonImmutable::now();

        $this->callEventService->append(
            $callLog,
            $providerId,
            $result->providerCallId.':created',
            CallEventType::CallCreated,
            [
                'direction' => $result->direction->value,
                'status' => $result->status->value,
            ],
            $occurredAt,
            1,
        );

        if ($result->status->value === 'ringing') {
            $this->callEventService->append(
                $callLog->refresh(),
                $providerId,
                $result->providerCallId.':ringing',
                CallEventType::CallRinging,
                [
                    'direction' => $result->direction->value,
                ],
                $occurredAt,
                2,
            );
        }

        return $callLog->refresh();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordStateTransition(
        TelephonyCallState $state,
        string $providerId,
        CallEventType $type,
        array $payload = []
    ): CallLog {
        $callLog = $this->callLogService->createFromProviderCall(
            new TelephonyCallResult(
                tenantId: $state->tenantId,
                callId: $state->callId,
                providerCallId: $state->providerCallId,
                status: $state->status,
                direction: $state->direction,
                from: $state->from,
                to: $state->to,
                correlationId: $state->correlationId,
                metadata: $state->metadata,
                createdAt: $state->updatedAt,
            ),
            $providerId,
        );

        $occurredAt = $state->updatedAt ? CarbonImmutable::parse($state->updatedAt) : CarbonImmutable::now();

        $this->callEventService->append(
            $callLog,
            $providerId,
            $this->eventId($state, $type),
            $type,
            $payload,
            $occurredAt,
        );

        return $callLog->refresh();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordDisposition(CallLog $callLog, string $providerId, CallEventType $type, array $payload = [], ?CarbonImmutable $occurredAt = null): CallLog
    {
        $occurredAt ??= CarbonImmutable::now();

        if (! isset($payload['disposition']) && in_array($type, [CallEventType::CallCompleted, CallEventType::CallCancelled], true)) {
            $payload['disposition'] = CallDisposition::NoAnswer->value;
        }

        $this->callEventService->append(
            $callLog,
            $providerId,
            $callLog->provider_call_id.':'.$type->value.':'.$occurredAt->timestamp,
            $type,
            $payload,
            $occurredAt,
        );

        return $callLog->refresh();
    }

    private function eventId(TelephonyCallState $state, CallEventType $type): string
    {
        $fingerprint = $state->updatedAt ?? $state->correlationId ?? 'state';

        return $state->providerCallId.':'.$type->value.':'.$fingerprint;
    }
}
