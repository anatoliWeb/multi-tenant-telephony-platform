<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyCallDirection;

class TelephonyCallOptions
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly TelephonyCallDirection $direction = TelephonyCallDirection::Outbound,
        public readonly ?string $correlationId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   direction: string,
     *   correlation_id: string|null,
     *   idempotency_key: string|null,
     *   metadata: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'direction' => $this->direction->value,
            'correlation_id' => $this->correlationId,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
        ];
    }
}
