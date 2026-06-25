<?php

namespace App\DTO\Telephony;

class TelephonyParticipantResult
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $conferenceId,
        public readonly string $participantKey,
        public readonly string $displayName,
        public readonly bool $muted = false,
        public readonly ?string $joinedAt = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   conference_id: string,
     *   participant_key: string,
     *   display_name: string,
     *   muted: bool,
     *   joined_at: string|null,
     *   metadata: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'conference_id' => $this->conferenceId,
            'participant_key' => $this->participantKey,
            'display_name' => $this->displayName,
            'muted' => $this->muted,
            'joined_at' => $this->joinedAt,
            'metadata' => $this->metadata,
        ];
    }
}
