<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;

class TelephonyValidationException extends TelephonyException
{
    public function __construct(
        string $message = 'Telephony payload is invalid.',
        ?string $providerCode = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($message, TelephonyFailureCode::ValidationFailed, $providerCode, $correlationId);
    }
}
