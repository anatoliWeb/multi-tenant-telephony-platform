<?php

namespace Tests\Feature\Telephony;

use App\Services\Telephony\TelephonyProviderRegistry;
use Tests\TestCase;

class TelephonyConfigurationTest extends TestCase
{
    public function test_configuration_has_safe_defaults_and_fake_provider_selection(): void
    {
        $this->assertFalse((bool) config('telephony.enabled'));
        $this->assertSame('fake', config('telephony.default_provider'));
        $this->assertSame('Fake Telephony Provider', config('telephony.providers.fake.display_name'));
        $this->assertSame([], config('telephony.providers.fake.failures'));
    }

    public function test_fake_provider_requires_no_real_secrets(): void
    {
        $serialized = json_encode(config('telephony.providers.fake'), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('password', strtolower($serialized));
        $this->assertStringNotContainsString('secret', strtolower($serialized));
        $this->assertStringNotContainsString('token', strtolower($serialized));
        $this->assertStringNotContainsString('freeswitch', strtolower($serialized));
    }

    public function test_invalid_default_provider_config_is_rejected(): void
    {
        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'missing');

        $this->expectException(\App\Exceptions\Telephony\TelephonyResourceNotFoundException::class);
        app(TelephonyProviderRegistry::class)->defaultProvider();
    }
}
