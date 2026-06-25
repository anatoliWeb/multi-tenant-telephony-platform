<?php

namespace App\Enums\Extensions;

enum ExtensionStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
