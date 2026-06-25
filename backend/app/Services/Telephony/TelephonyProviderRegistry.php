<?php

namespace App\Services\Telephony;

use App\Contracts\Telephony\CallControlProvider;
use App\Contracts\Telephony\ConferenceControlProvider;
use App\Contracts\Telephony\EndpointProvisioningProvider;
use App\Contracts\Telephony\TelephonyProvider;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Exceptions\Telephony\TelephonyProviderUnavailableException;
use App\Exceptions\Telephony\TelephonyResourceNotFoundException;

class TelephonyProviderRegistry
{
    /**
     * @param iterable<int, TelephonyProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $id = $provider->providerId();

            if (isset($this->providers[$id])) {
                throw new TelephonyConflictException(sprintf('Duplicate telephony provider registration for [%s].', $id));
            }

            $this->providers[$id] = $provider;
        }
    }

    /**
     * @var array<string, TelephonyProvider>
     */
    private array $providers = [];

    public function defaultProvider(): TelephonyProvider
    {
        $providerId = (string) config('telephony.default_provider', 'fake');

        return $this->provider($providerId);
    }

    public function provider(string $providerId): TelephonyProvider
    {
        $this->assertEnabled();

        if (! isset($this->providers[$providerId])) {
            throw new TelephonyResourceNotFoundException(sprintf('Telephony provider [%s] is not configured.', $providerId));
        }

        return $this->providers[$providerId];
    }

    public function endpointProvisioningProvider(?string $providerId = null): EndpointProvisioningProvider
    {
        $provider = $providerId === null ? $this->defaultProvider() : $this->provider($providerId);

        if (! $provider instanceof EndpointProvisioningProvider) {
            throw new TelephonyProviderUnavailableException('Selected telephony provider does not support endpoint provisioning.');
        }

        return $provider;
    }

    public function callControlProvider(?string $providerId = null): CallControlProvider
    {
        $provider = $providerId === null ? $this->defaultProvider() : $this->provider($providerId);

        if (! $provider instanceof CallControlProvider) {
            throw new TelephonyProviderUnavailableException('Selected telephony provider does not support call control.');
        }

        return $provider;
    }

    public function conferenceControlProvider(?string $providerId = null): ConferenceControlProvider
    {
        $provider = $providerId === null ? $this->defaultProvider() : $this->provider($providerId);

        if (! $provider instanceof ConferenceControlProvider) {
            throw new TelephonyProviderUnavailableException('Selected telephony provider does not support conference control.');
        }

        return $provider;
    }

    /**
     * @return array<int, TelephonyProvider>
     */
    public function providers(): array
    {
        $this->assertEnabled();

        return array_values($this->providers);
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('telephony.enabled', false)) {
            throw new TelephonyProviderUnavailableException('Telephony is disabled.');
        }
    }
}
