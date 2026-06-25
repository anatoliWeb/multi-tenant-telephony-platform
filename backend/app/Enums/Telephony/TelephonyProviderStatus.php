<?php

namespace App\Enums\Telephony;

enum TelephonyProviderStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
    case Disabled = 'disabled';
}
