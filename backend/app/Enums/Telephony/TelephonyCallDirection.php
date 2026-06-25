<?php

namespace App\Enums\Telephony;

enum TelephonyCallDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Internal = 'internal';
}
