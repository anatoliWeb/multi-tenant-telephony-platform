<?php

namespace App\Contracts\Telephony;

use App\DTO\Telephony\TelephonyProviderDescriptor;
use App\DTO\Telephony\TelephonyProviderHealth;
use App\Enums\Telephony\TelephonyCapability;

interface TelephonyProvider
{
    public function providerId(): string;

    public function displayName(): string;

    /**
     * @return array<int, TelephonyCapability>
     */
    public function capabilities(): array;

    public function version(): ?string;

    public function descriptor(): TelephonyProviderDescriptor;

    public function health(): TelephonyProviderHealth;
}
