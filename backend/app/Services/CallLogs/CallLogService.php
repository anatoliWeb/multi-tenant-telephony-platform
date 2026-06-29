<?php

namespace App\Services\CallLogs;

use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyCallResult;
use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\CallLog;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallLogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CallPartyResolver $partyResolver,
    ) {
    }

    public function createFromProviderCall(TelephonyCallResult $result, string $providerId): CallLog
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();

        return DB::transaction(function () use ($result, $providerId, $tenantId): CallLog {
            $existing = CallLog::query()
                ->forTenant($tenantId)
                ->where('provider_id', $providerId)
                ->where('provider_call_id', $result->providerCallId)
                ->lockForUpdate()
                ->first();

            $caller = $this->partyResolver->resolve($result->from, $result->direction, 'caller');
            $callee = $this->partyResolver->resolve($result->to, $result->direction, 'callee');

            if ($existing instanceof CallLog) {
                return $this->reconcileExisting($existing, $result, $caller, $callee);
            }

            return CallLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'provider_id' => $providerId,
                'provider_call_id' => $result->providerCallId,
                'correlation_id' => $result->correlationId,
                'idempotency_key' => $result->idempotencyKey,
                'direction' => $result->direction->value,
                'status' => $result->status->value,
                'disposition' => CallDisposition::Unknown->value,
                'from_number' => $caller['raw_number'],
                'from_normalized_number' => $caller['normalized_number'],
                'to_number' => $callee['raw_number'],
                'to_normalized_number' => $callee['normalized_number'],
                'caller_user_id' => $caller['user_id'],
                'callee_user_id' => $callee['user_id'],
                'caller_extension_id' => $caller['extension_id'],
                'callee_extension_id' => $callee['extension_id'],
                'caller_phone_number_id' => $caller['phone_number_id'],
                'callee_phone_number_id' => $callee['phone_number_id'],
                'caller_contact_id' => $caller['contact_id'],
                'callee_contact_id' => $callee['contact_id'],
                'started_at' => $result->createdAt ? CarbonImmutable::parse($result->createdAt) : now(),
                'ringing_at' => $result->direction === TelephonyCallDirection::Internal || $result->status->value === 'ringing'
                    ? ($result->createdAt ? CarbonImmutable::parse($result->createdAt) : now())
                    : null,
                'billing_status' => $result->direction === TelephonyCallDirection::Internal
                    ? CallBillingStatus::NonBillable->value
                    : CallBillingStatus::Unrated->value,
                'recording_available' => false,
                'metadata' => $result->metadata,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $caller
     * @param array<string, mixed> $callee
     */
    private function reconcileExisting(CallLog $callLog, TelephonyCallResult $result, array $caller, array $callee): CallLog
    {
        if ((string) $callLog->direction->value !== $result->direction->value) {
            throw new TelephonyConflictException('Provider call identity already exists with a different direction.');
        }

        $callLog->forceFill([
            'correlation_id' => $callLog->correlation_id ?? $result->correlationId,
            'idempotency_key' => $callLog->idempotency_key ?? $result->idempotencyKey,
            'from_number' => $callLog->from_number ?? $caller['raw_number'],
            'from_normalized_number' => $callLog->from_normalized_number ?? $caller['normalized_number'],
            'to_number' => $callLog->to_number ?? $callee['raw_number'],
            'to_normalized_number' => $callLog->to_normalized_number ?? $callee['normalized_number'],
            'caller_user_id' => $callLog->caller_user_id ?? $caller['user_id'],
            'callee_user_id' => $callLog->callee_user_id ?? $callee['user_id'],
            'caller_extension_id' => $callLog->caller_extension_id ?? $caller['extension_id'],
            'callee_extension_id' => $callLog->callee_extension_id ?? $callee['extension_id'],
            'caller_phone_number_id' => $callLog->caller_phone_number_id ?? $caller['phone_number_id'],
            'callee_phone_number_id' => $callLog->callee_phone_number_id ?? $callee['phone_number_id'],
            'caller_contact_id' => $callLog->caller_contact_id ?? $caller['contact_id'],
            'callee_contact_id' => $callLog->callee_contact_id ?? $callee['contact_id'],
        ])->save();

        return $callLog->refresh();
    }
}
