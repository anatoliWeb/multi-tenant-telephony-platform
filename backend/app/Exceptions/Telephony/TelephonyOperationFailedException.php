<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;

class TelephonyOperationFailedException extends TelephonyException
{
    public function __construct(
        string $message = 'Telephony operation failed.',
        ?string $providerCode = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($message, TelephonyFailureCode::OperationFailed, $providerCode, $correlationId);
    }
}
