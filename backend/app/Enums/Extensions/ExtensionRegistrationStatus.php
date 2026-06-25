<?php

namespace App\Enums\Extensions;

enum ExtensionRegistrationStatus: string
{
    case Unknown = 'unknown';
    case Registered = 'registered';
    case Unregistered = 'unregistered';
}
