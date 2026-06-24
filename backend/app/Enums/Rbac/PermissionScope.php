<?php

namespace App\Enums\Rbac;

enum PermissionScope: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
}

