<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;

class TelephonyUnsupportedCapabilityException extends TelephonyException
{
    public function __construct(
        string $message = 'Telephony capability is not supported.',
        ?string $providerCode = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($message, TelephonyFailureCode::UnsupportedCapability, $providerCode, $correlationId);
    }
}
