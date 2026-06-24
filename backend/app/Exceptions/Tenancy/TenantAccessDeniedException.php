<?php

namespace App\Exceptions\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;

class TenantAccessDeniedException extends AuthorizationException
{
}
