<?php

namespace App\Services\CallLogs;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\CallLogs\CallEventType;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\CallLog;
use Carbon\CarbonImmutable;

class CallLifecycleService
{
    public function __construct(
        private readonly CallDurationCalculator $durationCalculator,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function applyEvent(CallLog $callLog, CallEventType $type, array $payload = [], ?CarbonImmutable $occurredAt = null): CallLog
    {
        $occurredAt ??= CarbonImmutable::now();

        match ($type) {
            CallEventType::CallCreated, CallEventType::CallInitiated => $this->applyCreated($callLog, $occurredAt),
            CallEventType::CallRinging => $this->applyRinging($callLog, $occurredAt),
            CallEventType::CallAnswered => $this->applyAnswered($callLog, $occurredAt),
            CallEventType::CallHeld => $this->applyHeld($callLog),
            CallEventType::CallResumed => $this->applyResumed($callLog),
            CallEventType::CallCompleted => $this->applyCompleted($callLog, $payload, $occurredAt),
            CallEventType::CallFailed => $this->applyFailed($callLog, $payload, $occurredAt),
            CallEventType::CallCancelled => $this->applyCancelled($callLog, $payload, $occurredAt),
        };

        $callLog->forceFill($this->durationCalculator->calculate($callLog));
        $callLog->save();

        return $callLog->refresh();
    }

    private function applyCreated(CallLog $callLog, CarbonImmutable $occurredAt): void
    {
        if (! $callLog->started_at || $occurredAt->lt($callLog->started_at)) {
            $callLog->started_at = $occurredAt;
        }

        if ($callLog->status === null || $callLog->status === TelephonyCallStatus::Created) {
            $callLog->status = TelephonyCallStatus::Created;
        }
    }

    private function applyRinging(CallLog $callLog, CarbonImmutable $occurredAt): void
    {
        if (! $callLog->started_at) {
            $callLog->started_at = $occurredAt;
        }

        if (! $callLog->ringing_at || $occurredAt->lt($callLog->ringing_at)) {
            $callLog->ringing_at = $occurredAt;
        }

        if (! in_array($callLog->status, [TelephonyCallStatus::Answered, TelephonyCallStatus::Held, TelephonyCallStatus::Completed, TelephonyCallStatus::Failed, TelephonyCallStatus::Cancelled], true)) {
            $callLog->status = TelephonyCallStatus::Ringing;
        }
    }

    private function applyAnswered(CallLog $callLog, CarbonImmutable $occurredAt): void
    {
        if (! $callLog->started_at) {
            $callLog->started_at = $occurredAt;
        }

        if (! $callLog->ringing_at || $occurredAt->lt($callLog->ringing_at)) {
            $callLog->ringing_at = $occurredAt;
        }

        if (! $callLog->answered_at || $occurredAt->lt($callLog->answered_at)) {
            $callLog->answered_at = $occurredAt;
        }

        if (! in_array($callLog->status, [TelephonyCallStatus::Completed, TelephonyCallStatus::Failed, TelephonyCallStatus::Cancelled], true)) {
            $callLog->status = TelephonyCallStatus::Answered;
            $callLog->disposition = CallDisposition::Answered;
        }
    }

    private function applyHeld(CallLog $callLog): void
    {
        if (! in_array($callLog->status, [TelephonyCallStatus::Completed, TelephonyCallStatus::Failed, TelephonyCallStatus::Cancelled], true)) {
            $callLog->status = TelephonyCallStatus::Held;
        }
    }

    private function applyResumed(CallLog $callLog): void
    {
        if (! in_array($callLog->status, [TelephonyCallStatus::Completed, TelephonyCallStatus::Failed, TelephonyCallStatus::Cancelled], true)) {
            $callLog->status = TelephonyCallStatus::Answered;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyCompleted(CallLog $callLog, array $payload, CarbonImmutable $occurredAt): void
    {
        if (! $callLog->ended_at || $occurredAt->gt($callLog->ended_at)) {
            $callLog->ended_at = $occurredAt;
        }

        $callLog->status = TelephonyCallStatus::Completed;
        $callLog->disposition = $callLog->answered_at
            ? CallDisposition::Answered
            : $this->resolveDisposition($payload, CallDisposition::NoAnswer);
        $callLog->hangup_cause = isset($payload['hangup_cause']) ? (string) $payload['hangup_cause'] : $callLog->hangup_cause;
        $callLog->billing_status = $callLog->billing_status === CallBillingStatus::NonBillable
            ? CallBillingStatus::NonBillable
            : CallBillingStatus::Unrated;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyFailed(CallLog $callLog, array $payload, CarbonImmutable $occurredAt): void
    {
        if (! $callLog->ended_at || $occurredAt->gt($callLog->ended_at)) {
            $callLog->ended_at = $occurredAt;
        }

        $callLog->status = TelephonyCallStatus::Failed;
        $callLog->disposition = $this->resolveDisposition($payload, CallDisposition::Failed);
        $callLog->failure_code = isset($payload['failure_code']) ? (string) $payload['failure_code'] : $callLog->failure_code;
        $callLog->failure_message = isset($payload['failure_message']) ? mb_substr((string) $payload['failure_message'], 0, 255) : $callLog->failure_message;
        $callLog->billing_status = CallBillingStatus::Failed;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyCancelled(CallLog $callLog, array $payload, CarbonImmutable $occurredAt): void
    {
        if (! $callLog->ended_at || $occurredAt->gt($callLog->ended_at)) {
            $callLog->ended_at = $occurredAt;
        }

        $callLog->status = TelephonyCallStatus::Cancelled;
        $callLog->disposition = $this->resolveDisposition($payload, CallDisposition::Cancelled);
        $callLog->hangup_cause = isset($payload['hangup_cause']) ? (string) $payload['hangup_cause'] : $callLog->hangup_cause;
        $callLog->billing_status = $callLog->billing_status === CallBillingStatus::NonBillable
            ? CallBillingStatus::NonBillable
            : CallBillingStatus::Unrated;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveDisposition(array $payload, CallDisposition $fallback): CallDisposition
    {
        $value = isset($payload['disposition']) ? (string) $payload['disposition'] : null;

        return $value && CallDisposition::tryFrom($value)
            ? CallDisposition::from($value)
            : $fallback;
    }
}
