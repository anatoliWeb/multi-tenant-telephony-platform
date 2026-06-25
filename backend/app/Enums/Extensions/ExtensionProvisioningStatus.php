<?php

namespace App\Enums\Extensions;

enum ExtensionProvisioningStatus: string
{
    case Pending = 'pending';
    case Provisioned = 'provisioned';
    case Suspended = 'suspended';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
