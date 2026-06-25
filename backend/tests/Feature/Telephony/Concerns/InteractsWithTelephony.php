<?php

namespace Tests\Feature\Telephony\Concerns;

use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyEndpointStatus;
use App\Models\Tenant;
use App\Services\Telephony\FakeTelephonyProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Str;

trait InteractsWithTelephony
{
    protected function setTelephonyTenant(string $tenantId = 'tenant-a', string $slug = 'tenant-a'): Tenant
    {
        $tenant = new Tenant();
        $tenant->forceFill([
            'id' => $tenantId,
            'name' => strtoupper(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
        ]);
        $tenant->exists = true;

        app(TenantContext::class)->setTenant($tenant);

        return $tenant;
    }

    protected function clearTelephonyTenant(): void
    {
        app(TenantContext::class)->clear();
    }

    protected function resetFakeTelephony(): FakeTelephonyProvider
    {
        $provider = app(FakeTelephonyProvider::class);
        $provider->resetState();

        return $provider;
    }

    protected function fakeEndpointInput(array $overrides = []): \App\DTO\Telephony\TelephonyEndpointInput
    {
        return new \App\DTO\Telephony\TelephonyEndpointInput(
            tenantId: $overrides['tenantId'] ?? 'tenant-a',
            endpointKey: $overrides['endpointKey'] ?? 'agent-1001',
            address: $overrides['address'] ?? '1001@example.test',
            displayName: $overrides['displayName'] ?? 'Agent 1001',
            desiredStatus: $overrides['desiredStatus'] ?? TelephonyEndpointStatus::Active,
            correlationId: $overrides['correlationId'] ?? (string) Str::uuid(),
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
            metadata: $overrides['metadata'] ?? [],
        );
    }

    protected function fakeCallOptions(array $overrides = []): \App\DTO\Telephony\TelephonyCallOptions
    {
        return new \App\DTO\Telephony\TelephonyCallOptions(
            tenantId: $overrides['tenantId'] ?? 'tenant-a',
            direction: $overrides['direction'] ?? TelephonyCallDirection::Outbound,
            correlationId: $overrides['correlationId'] ?? (string) Str::uuid(),
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
            metadata: $overrides['metadata'] ?? [],
        );
    }
}
