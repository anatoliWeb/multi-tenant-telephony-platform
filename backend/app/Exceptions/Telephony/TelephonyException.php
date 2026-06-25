<?php

namespace App\Exceptions\Telephony;

use App\Enums\Telephony\TelephonyFailureCode;
use RuntimeException;

class TelephonyException extends RuntimeException
{
    public function __construct(
        string $message = 'Telephony operation failed.',
        protected readonly TelephonyFailureCode $failureCode = TelephonyFailureCode::OperationFailed,
        protected readonly ?string $providerCode = null,
        protected readonly ?string $correlationId = null,
    ) {
        parent::__construct($message);
    }

    public function failureCode(): TelephonyFailureCode
    {
        return $this->failureCode;
    }

    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * @return array{message: string, failure_code: string, correlation_id: string|null}
     */
    public function toSafeArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'failure_code' => $this->failureCode->value,
            'correlation_id' => $this->correlationId,
        ];
    }
}
