<?php

namespace App\Exceptions\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantContextRequiredException extends HttpException
{
    public function __construct(string $message = 'Tenant context is required')
    {
        parent::__construct(422, $message);
    }
}
