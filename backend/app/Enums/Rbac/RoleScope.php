<?php

namespace App\Enums\Rbac;

enum RoleScope: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
}

