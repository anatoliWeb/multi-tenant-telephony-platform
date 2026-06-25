<?php

namespace Tests\Feature\Telephony;

use App\Enums\Telephony\TelephonyCapability;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Exceptions\Telephony\TelephonyProviderUnavailableException;
use App\Exceptions\Telephony\TelephonyResourceNotFoundException;
use App\Services\Telephony\FakeTelephonyProvider;
use App\Services\Telephony\TelephonyProviderRegistry;
use Tests\TestCase;

class TelephonyProviderRegistryTest extends TestCase
{
    public function test_container_resolves_registry_and_default_provider(): void
    {
        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'fake');

        $registry = app(TelephonyProviderRegistry::class);

        $this->assertInstanceOf(TelephonyProviderRegistry::class, $registry);
        $this->assertSame('fake', $registry->defaultProvider()->providerId());
        $this->assertInstanceOf(FakeTelephonyProvider::class, $registry->callControlProvider());
    }

    public function test_disabled_telephony_fails_safely(): void
    {
        config()->set('telephony.enabled', false);

        $this->expectException(TelephonyProviderUnavailableException::class);
        app(TelephonyProviderRegistry::class)->defaultProvider();
    }

    public function test_unknown_provider_is_rejected(): void
    {
        config()->set('telephony.enabled', true);

        $this->expectException(TelephonyResourceNotFoundException::class);
        app(TelephonyProviderRegistry::class)->provider('missing');
    }

    public function test_duplicate_provider_registration_is_rejected(): void
    {
        $provider = new FakeTelephonyProvider();

        $this->expectException(TelephonyConflictException::class);
        new TelephonyProviderRegistry([$provider, $provider]);
    }

    public function test_capability_lookup_reflects_provider_configuration(): void
    {
        config()->set('telephony.enabled', true);
        config()->set('telephony.providers.fake.capabilities', [
            TelephonyCapability::EndpointProvisioning->value,
            TelephonyCapability::CallOrigination->value,
        ]);

        $descriptor = app(TelephonyProviderRegistry::class)->defaultProvider()->descriptor();

        $this->assertSame(
            [
                TelephonyCapability::EndpointProvisioning->value,
                TelephonyCapability::CallOrigination->value,
            ],
            $descriptor->toArray()['capabilities']
        );
    }
}
