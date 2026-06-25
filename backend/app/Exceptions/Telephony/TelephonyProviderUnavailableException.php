<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;

class TelephonyProviderUnavailableException extends TelephonyException
{
    public function __construct(
        string $message = 'Telephony provider is unavailable.',
        ?string $providerCode = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($message, TelephonyFailureCode::ProviderUnavailable, $providerCode, $correlationId);
    }
}
