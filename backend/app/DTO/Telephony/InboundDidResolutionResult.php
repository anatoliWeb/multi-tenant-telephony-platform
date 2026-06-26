<?php

namespace App\DTO\Telephony;

use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;

class InboundDidResolutionResult
{
    public function __construct(
        public readonly PhoneNumber $phoneNumber,
        public readonly Tenant $tenant,
        public readonly ?User $assignedUser,
        public readonly string $status,
        public readonly bool $routingAllowed,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phone_number' => [
                'id' => $this->phoneNumber->id,
                'uuid' => $this->phoneNumber->uuid,
                'number' => $this->phoneNumber->number,
                'normalized_number' => $this->phoneNumber->normalized_number,
                'display_number' => $this->phoneNumber->display_number,
                'type' => $this->phoneNumber->type?->value ?? $this->phoneNumber->type,
                'status' => $this->phoneNumber->status?->value ?? $this->phoneNumber->status,
                'assignment_status' => $this->phoneNumber->assignment_status?->value ?? $this->phoneNumber->assignment_status,
                'is_primary' => (bool) $this->phoneNumber->is_primary,
            ],
            'tenant' => [
                'id' => $this->tenant->getKey(),
                'slug' => $this->tenant->slug,
                'name' => $this->tenant->name,
            ],
            'assigned_user' => $this->assignedUser ? [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
                'email' => $this->assignedUser->email,
            ] : null,
            'status' => $this->status,
            'routing_allowed' => $this->routingAllowed,
        ];
    }
}
