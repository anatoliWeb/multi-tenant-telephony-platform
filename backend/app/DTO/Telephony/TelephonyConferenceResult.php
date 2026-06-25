<?php

namespace App\DTO\Telephony;

class TelephonyConferenceResult
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $conferenceId,
        public readonly string $providerConferenceId,
        public readonly string $conferenceKey,
        public readonly string $displayName,
        public readonly int $participantCount,
        public readonly ?string $correlationId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
        public readonly ?string $createdAt = null,
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   conference_id: string,
     *   provider_conference_id: string,
     *   conference_key: string,
     *   display_name: string,
     *   participant_count: int,
     *   correlation_id: string|null,
     *   idempotency_key: string|null,
     *   metadata: array<string, scalar|null>,
     *   created_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'conference_id' => $this->conferenceId,
            'provider_conference_id' => $this->providerConferenceId,
            'conference_key' => $this->conferenceKey,
            'display_name' => $this->displayName,
            'participant_count' => $this->participantCount,
            'correlation_id' => $this->correlationId,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
        ];
    }
}
