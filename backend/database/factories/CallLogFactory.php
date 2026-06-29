<?php

namespace Database\Factories;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\CallLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CallLog>
 */
class CallLogFactory extends Factory
{
    protected $model = CallLog::class;

    public function definition(): array
    {
        $startedAt = now()->subMinutes(5);
        $answeredAt = $startedAt->copy()->addSeconds(10);
        $endedAt = $answeredAt->copy()->addSeconds(120);

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'provider_id' => 'fake',
            'provider_call_id' => 'provider-call-'.Str::lower(Str::random(12)),
            'correlation_id' => (string) Str::uuid(),
            'direction' => TelephonyCallDirection::Outbound->value,
            'status' => TelephonyCallStatus::Completed->value,
            'disposition' => CallDisposition::Answered->value,
            'from_number' => '+15550001001',
            'from_normalized_number' => '+15550001001',
            'to_number' => '+15550009999',
            'to_normalized_number' => '+15550009999',
            'caller_user_id' => User::factory(),
            'callee_user_id' => null,
            'started_at' => $startedAt,
            'ringing_at' => $startedAt,
            'answered_at' => $answeredAt,
            'ended_at' => $endedAt,
            'ringing_seconds' => 10,
            'talk_seconds' => 120,
            'billable_seconds' => 120,
            'total_seconds' => 130,
            'billing_status' => CallBillingStatus::Unrated->value,
            'recording_available' => false,
            'metadata' => [],
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => TelephonyCallDirection::Inbound->value,
            'from_number' => '+15550009999',
            'from_normalized_number' => '+15550009999',
            'to_number' => '+15550001001',
            'to_normalized_number' => '+15550001001',
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'direction' => TelephonyCallDirection::Outbound->value,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn () => [
            'direction' => TelephonyCallDirection::Internal->value,
            'from_number' => '2001',
            'from_normalized_number' => '2001',
            'to_number' => '2002',
            'to_normalized_number' => '2002',
            'billing_status' => CallBillingStatus::NonBillable->value,
        ]);
    }

    public function answered(): static
    {
        return $this->state(fn () => [
            'status' => TelephonyCallStatus::Completed->value,
            'disposition' => CallDisposition::Answered->value,
        ]);
    }

    public function missed(): static
    {
        $startedAt = now()->subMinutes(5);
        $endedAt = $startedAt->copy()->addSeconds(30);

        return $this->state(fn () => [
            'status' => TelephonyCallStatus::Completed->value,
            'disposition' => CallDisposition::NoAnswer->value,
            'answered_at' => null,
            'ended_at' => $endedAt,
            'talk_seconds' => 0,
            'billable_seconds' => 0,
            'total_seconds' => 30,
        ]);
    }

    public function failed(): static
    {
        $startedAt = now()->subMinutes(5);
        $endedAt = $startedAt->copy()->addSeconds(5);

        return $this->state(fn () => [
            'status' => TelephonyCallStatus::Failed->value,
            'disposition' => CallDisposition::Failed->value,
            'answered_at' => null,
            'ended_at' => $endedAt,
            'talk_seconds' => 0,
            'billable_seconds' => 0,
            'total_seconds' => 5,
            'billing_status' => CallBillingStatus::Failed->value,
        ]);
    }

    public function forTenant(Tenant|string $tenant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->getKey() : $tenant,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'caller_user_id' => $user->getKey(),
        ]);
    }
}
