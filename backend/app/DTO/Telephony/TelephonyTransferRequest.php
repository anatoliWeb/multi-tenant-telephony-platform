<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyTransferType;

class TelephonyTransferRequest
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $callId,
        public readonly TelephonyCallParty $target,
        public readonly TelephonyTransferType $type = TelephonyTransferType::Blind,
        public readonly ?string $correlationId = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   call_id: string,
     *   target: array<string, string|null>,
     *   type: string,
     *   correlation_id: string|null,
     *   metadata: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'call_id' => $this->callId,
            'target' => $this->target->toArray(),
            'type' => $this->type->value,
            'correlation_id' => $this->correlationId,
            'metadata' => $this->metadata,
        ];
    }
}
