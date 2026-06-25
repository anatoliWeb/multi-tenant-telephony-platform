<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;

class TelephonyResourceNotFoundException extends TelephonyException
{
    public function __construct(
        string $message = 'Telephony resource was not found.',
        ?string $providerCode = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($message, TelephonyFailureCode::ResourceNotFound, $providerCode, $correlationId);
    }
}
