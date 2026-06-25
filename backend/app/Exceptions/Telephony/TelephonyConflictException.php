<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;

class TelephonyConflictException extends TelephonyException
{
    public function __construct(
        string $message = 'Telephony operation conflicted with an existing request.',
        ?string $providerCode = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($message, TelephonyFailureCode::Conflict, $providerCode, $correlationId);
    }
}
