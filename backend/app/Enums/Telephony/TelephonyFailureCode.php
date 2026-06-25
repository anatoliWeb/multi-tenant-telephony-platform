<?php

namespace App\Enums\Telephony;

enum TelephonyFailureCode: string
{
    case ProviderUnavailable = 'provider_unavailable';
    case UnsupportedCapability = 'unsupported_capability';
    case ValidationFailed = 'validation_failed';
    case OperationFailed = 'operation_failed';
    case ResourceNotFound = 'resource_not_found';
    case Conflict = 'conflict';
    case TelephonyDisabled = 'telephony_disabled';
}
