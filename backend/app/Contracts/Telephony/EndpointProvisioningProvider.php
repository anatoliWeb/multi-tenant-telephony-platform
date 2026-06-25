<?php

namespace App\Contracts\Telephony;

use App\DTO\Telephony\TelephonyEndpointInput;
use App\DTO\Telephony\TelephonyEndpointResult;

interface EndpointProvisioningProvider
{
    public function createEndpoint(TelephonyEndpointInput $input): TelephonyEndpointResult;

    public function updateEndpoint(string $endpointKey, TelephonyEndpointInput $input): TelephonyEndpointResult;

    public function suspendEndpoint(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult;

    public function activateEndpoint(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult;

    public function deleteEndpoint(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult;

    public function fetchEndpointState(string $tenantId, string $endpointKey, ?string $correlationId = null): TelephonyEndpointResult;
}
