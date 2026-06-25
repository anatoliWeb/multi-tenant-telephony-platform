<?php

namespace App\Enums\Contacts;

enum ContactStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Blocked = 'blocked';
}
