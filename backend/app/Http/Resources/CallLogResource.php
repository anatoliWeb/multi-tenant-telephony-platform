<?php

namespace App\Http\Resources;

use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CallLog
 */
class CallLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'provider_id' => $this->provider_id,
            'provider_call_id' => $this->provider_call_id,
            'correlation_id' => $this->correlation_id,
            'direction' => $this->direction?->value ?? $this->direction,
            'status' => $this->status?->value ?? $this->status,
            'disposition' => $this->disposition?->value ?? $this->disposition,
            'from_number' => $this->from_number,
            'from_normalized_number' => $this->from_normalized_number,
            'to_number' => $this->to_number,
            'to_normalized_number' => $this->to_normalized_number,
            'caller' => $this->partySummary('caller'),
            'callee' => $this->partySummary('callee'),
            'started_at' => $this->started_at?->toISOString(),
            'ringing_at' => $this->ringing_at?->toISOString(),
            'answered_at' => $this->answered_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'ringing_seconds' => (int) $this->ringing_seconds,
            'talk_seconds' => (int) $this->talk_seconds,
            'billable_seconds' => (int) $this->billable_seconds,
            'total_seconds' => (int) $this->total_seconds,
            'hangup_cause' => $this->hangup_cause,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'billing_status' => $this->billing_status?->value ?? $this->billing_status,
            'recording_available' => (bool) $this->recording_available,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function partySummary(string $side): array
    {
        $userRelation = $side.'User';
        $extensionRelation = $side.'Extension';
        $phoneNumberRelation = $side.'PhoneNumber';
        $contactRelation = $side.'Contact';

        $user = $this->{$userRelation};
        $extension = $this->{$extensionRelation};
        $phoneNumber = $this->{$phoneNumberRelation};
        $contact = $this->{$contactRelation};

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'extension' => $extension ? [
                'id' => $extension->id,
                'number' => $extension->number,
                'label' => $extension->label,
            ] : null,
            'phone_number' => $phoneNumber ? [
                'id' => $phoneNumber->id,
                'display_number' => $phoneNumber->display_number,
                'number' => $phoneNumber->number,
            ] : null,
            'contact' => $contact ? [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
            ] : null,
        ];
    }
}
