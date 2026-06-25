<?php

namespace App\Enums\Telephony;

enum TelephonyTransferType: string
{
    case Blind = 'blind';
    case Attended = 'attended';
}
