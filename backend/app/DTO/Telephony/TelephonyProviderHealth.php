<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyProviderStatus;

class TelephonyProviderHealth
{
    /**
     * @param array<int, string> $degradedReasons
     */
    public function __construct(
        public readonly string $providerId,
        public readonly TelephonyProviderStatus $status,
        public readonly ?int $latencyMs,
        public readonly array $degradedReasons,
        public readonly ?string $checkedAt = null,
    ) {
    }

    /**
     * @return array{
     *   provider_id: string,
     *   status: string,
     *   latency_ms: int|null,
     *   degraded_reasons: array<int, string>,
     *   checked_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'status' => $this->status->value,
            'latency_ms' => $this->latencyMs,
            'degraded_reasons' => $this->degradedReasons,
            'checked_at' => $this->checkedAt,
        ];
    }
}
