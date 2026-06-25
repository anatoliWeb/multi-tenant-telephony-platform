<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyEndpointStatus;

class TelephonyEndpointResult
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $endpointKey,
        public readonly string $providerResourceId,
        public readonly string $address,
        public readonly string $displayName,
        public readonly TelephonyEndpointStatus $status,
        public readonly ?string $correlationId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   endpoint_key: string,
     *   provider_resource_id: string,
     *   address: string,
     *   display_name: string,
     *   status: string,
     *   correlation_id: string|null,
     *   idempotency_key: string|null,
     *   metadata: array<string, scalar|null>,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'endpoint_key' => $this->endpointKey,
            'provider_resource_id' => $this->providerResourceId,
            'address' => $this->address,
            'display_name' => $this->displayName,
            'status' => $this->status->value,
            'correlation_id' => $this->correlationId,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
