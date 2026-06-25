<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;

class TelephonyCallState
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $callId,
        public readonly string $providerCallId,
        public readonly TelephonyCallStatus $status,
        public readonly TelephonyCallDirection $direction,
        public readonly TelephonyCallParty $from,
        public readonly TelephonyCallParty $to,
        public readonly bool $muted = false,
        public readonly ?string $correlationId = null,
        public readonly array $metadata = [],
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   call_id: string,
     *   provider_call_id: string,
     *   status: string,
     *   direction: string,
     *   from: array<string, string|null>,
     *   to: array<string, string|null>,
     *   muted: bool,
     *   correlation_id: string|null,
     *   metadata: array<string, scalar|null>,
     *   updated_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'call_id' => $this->callId,
            'provider_call_id' => $this->providerCallId,
            'status' => $this->status->value,
            'direction' => $this->direction->value,
            'from' => $this->from->toArray(),
            'to' => $this->to->toArray(),
            'muted' => $this->muted,
            'correlation_id' => $this->correlationId,
            'metadata' => $this->metadata,
            'updated_at' => $this->updatedAt,
        ];
    }
}
