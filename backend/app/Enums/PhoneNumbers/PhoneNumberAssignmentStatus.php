<?php

namespace App\Enums\PhoneNumbers;

enum PhoneNumberAssignmentStatus: string
{
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
}
