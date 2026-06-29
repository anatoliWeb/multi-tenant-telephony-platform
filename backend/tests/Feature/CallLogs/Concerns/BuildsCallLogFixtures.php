<?php

namespace Tests\Feature\CallLogs\Concerns;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\CallLogs\CallEventType;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\CallEvent;
use App\Models\CallLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\PhoneNumbers\Concerns\BuildsPhoneNumberFixtures;

trait BuildsCallLogFixtures
{
    use BuildsPhoneNumberFixtures;

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createCallLogFixture(Tenant $tenant, User $owner, array $overrides = []): CallLog
    {
        $startedAt = $overrides['started_at'] ?? now()->subMinutes(15);
        $answeredAt = array_key_exists('answered_at', $overrides)
            ? $overrides['answered_at']
            : $startedAt->copy()->addSeconds(5);
        $endedAt = $overrides['ended_at'] ?? ($answeredAt ? $answeredAt->copy()->addSeconds(60) : $startedAt->copy()->addSeconds(30));
        $status = $overrides['status'] ?? TelephonyCallStatus::Completed;
        $direction = $overrides['direction'] ?? TelephonyCallDirection::Outbound;
        $disposition = $overrides['disposition'] ?? CallDisposition::Answered;

        return CallLog::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'provider_id' => 'fake',
            'provider_call_id' => 'provider-call-'.Str::lower(Str::random(8)),
            'correlation_id' => (string) Str::uuid(),
            'direction' => $direction->value,
            'status' => $status->value,
            'disposition' => $disposition->value,
            'from_number' => '+15550001001',
            'from_normalized_number' => '+15550001001',
            'to_number' => '+15550009999',
            'to_normalized_number' => '+15550009999',
            'caller_user_id' => $owner->getKey(),
            'callee_user_id' => null,
            'started_at' => $startedAt,
            'ringing_at' => $startedAt,
            'answered_at' => $answeredAt,
            'ended_at' => $endedAt,
            'ringing_seconds' => $answeredAt ? max(0, $answeredAt->diffInSeconds($startedAt)) : 0,
            'talk_seconds' => $answeredAt ? max(0, $endedAt->diffInSeconds($answeredAt)) : 0,
            'billable_seconds' => $answeredAt ? max(0, $endedAt->diffInSeconds($answeredAt)) : 0,
            'total_seconds' => max(0, $endedAt->diffInSeconds($startedAt)),
            'billing_status' => $direction === TelephonyCallDirection::Internal
                ? CallBillingStatus::NonBillable->value
                : ($status === TelephonyCallStatus::Failed ? CallBillingStatus::Failed->value : CallBillingStatus::Unrated->value),
            'recording_available' => false,
            'metadata' => [],
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function createCallEventFixture(
        CallLog $callLog,
        CallEventType $type = CallEventType::CallCreated,
        array $payload = [],
        ?string $providerEventId = null
    ): CallEvent {
        return CallEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $callLog->tenant_id,
            'call_log_id' => $callLog->getKey(),
            'provider_event_id' => $providerEventId ?? 'provider-event-'.Str::lower(Str::random(8)),
            'provider_id' => $callLog->provider_id,
            'type' => $type->value,
            'occurred_at' => now(),
            'sequence' => 1,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
