<?php

namespace Tests\Feature\Api;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPreloadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_settings_preload_is_public_and_filters_private_records(): void
    {
        SystemSetting::query()->create([
            'key' => 'branding.app_name',
            'label' => 'Branding App Name',
            'group' => 'branding',
            'type' => 'string',
            'value' => 'Telephony Platform',
            'is_frontend' => true,
            'is_backend' => false,
            'is_public' => true,
            'is_encrypted' => false,
            'priority' => 100,
            'is_active' => true,
            'is_system' => true,
        ]);

        SystemSetting::query()->create([
            'key' => 'security.internal_token',
            'label' => 'Security Internal Token',
            'group' => 'security',
            'type' => 'string',
            'value' => 'secret-token',
            'is_frontend' => true,
            'is_backend' => true,
            'is_public' => false,
            'is_encrypted' => true,
            'priority' => 100,
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->getJson('/api/v1/settings/preload')
            ->assertOk()
            ->assertJsonPath('data.channel', 'frontend')
            ->assertJsonPath('data.settings.branding.app_name', 'Telephony Platform')
            ->assertJsonMissingPath('data.settings.security.internal_token');
    }
}
