<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyEndpointStatus;

class TelephonyEndpointInput
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $endpointKey,
        public readonly string $address,
        public readonly string $displayName,
        public readonly TelephonyEndpointStatus $desiredStatus = TelephonyEndpointStatus::Active,
        public readonly ?string $correlationId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   endpoint_key: string,
     *   address: string,
     *   display_name: string,
     *   desired_status: string,
     *   correlation_id: string|null,
     *   idempotency_key: string|null,
     *   metadata: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'endpoint_key' => $this->endpointKey,
            'address' => $this->address,
            'display_name' => $this->displayName,
            'desired_status' => $this->desiredStatus->value,
            'correlation_id' => $this->correlationId,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
        ];
    }
}
