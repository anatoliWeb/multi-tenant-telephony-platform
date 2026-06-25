<?php

namespace App\Enums\Telephony;

enum TelephonyCallStatus: string
{
    case Created = 'created';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Held = 'held';
    case Completed = 'completed';
    case Failed = 'failed';
}
