<?php

namespace App\DTO\Telephony;

class TelephonyConferenceInput
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $conferenceKey,
        public readonly string $displayName,
        public readonly ?string $correlationId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   conference_key: string,
     *   display_name: string,
     *   correlation_id: string|null,
     *   idempotency_key: string|null,
     *   metadata: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'conference_key' => $this->conferenceKey,
            'display_name' => $this->displayName,
            'correlation_id' => $this->correlationId,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
        ];
    }
}
