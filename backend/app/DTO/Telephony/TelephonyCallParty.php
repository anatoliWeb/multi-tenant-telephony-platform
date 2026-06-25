<?php

namespace App\DTO\Telephony;

class TelephonyCallParty
{
    public function __construct(
        public readonly string $identifier,
        public readonly ?string $displayName = null,
        public readonly ?string $number = null,
        public readonly ?string $endpointKey = null,
    ) {
    }

    /**
     * @return array{
     *   identifier: string,
     *   display_name: string|null,
     *   number: string|null,
     *   endpoint_key: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'display_name' => $this->displayName,
            'number' => $this->number,
            'endpoint_key' => $this->endpointKey,
        ];
    }
}
