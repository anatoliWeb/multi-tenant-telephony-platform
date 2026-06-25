<?php

namespace App\Enums\Telephony;

enum TelephonyEndpointStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';
    case Failed = 'failed';
}
