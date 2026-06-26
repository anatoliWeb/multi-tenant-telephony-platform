<?php

namespace App\Enums\PhoneNumbers;

enum PhoneNumberStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Active = 'active';
    case Suspended = 'suspended';
    case Released = 'released';
    case Failed = 'failed';
}
