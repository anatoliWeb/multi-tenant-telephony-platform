<?php

namespace App\DTO\Telephony;

use App\Enums\Telephony\TelephonyCapability;

class TelephonyProviderDescriptor
{
    /**
     * @param array<int, TelephonyCapability> $capabilities
     */
    public function __construct(
        public readonly string $providerId,
        public readonly string $displayName,
        public readonly array $capabilities,
        public readonly ?string $version = null,
    ) {
    }

    /**
     * @return array{
     *   provider_id: string,
     *   display_name: string,
     *   capabilities: array<int, string>,
     *   version: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'display_name' => $this->displayName,
            'capabilities' => array_map(
                static fn (TelephonyCapability $capability): string => $capability->value,
                $this->capabilities
            ),
            'version' => $this->version,
        ];
    }
}
