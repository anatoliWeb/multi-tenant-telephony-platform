<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiToolingTest extends TestCase
{
    use RefreshDatabase;

    public function test_scramble_openapi_tooling_foundation(): void
    {
        $this->assertTrue(class_exists(\Dedoc\Scramble\ScrambleServiceProvider::class));
        $this->assertSame('api/v1', config('scramble.api_path'));
        $this->assertSame('Multi-Tenant Telephony Platform API', config('scramble.ui.title'));

        $docsRoute = $this->get('/docs/api');
        $docsRoute->assertOk();

        $jsonResponse = $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['openapi', 'info', 'paths']);

        $spec = $jsonResponse->json();
        $this->assertIsArray($spec);
        $this->assertStringStartsWith('3.', (string) data_get($spec, 'openapi'));
        $this->assertSame('Multi-Tenant Telephony Platform API', data_get($spec, 'info.title'));
        $this->assertSame('v1', data_get($spec, 'info.version'));

        $paths = array_keys((array) data_get($spec, 'paths', []));
        $this->assertNotEmpty($paths);
        $this->assertTrue(
            collect($paths)->contains('/api/v1/chat/conversations/{conversation}/messages')
                || collect($paths)->contains('/v1/chat/conversations/{conversation}/messages')
                || collect($paths)->contains('/chat/conversations/{conversation}/messages'),
            'Spec should include chat send/list route.'
        );

        $this->assertFalse(collect($paths)->contains('/broadcasting/auth'));
        $this->assertFalse(collect($paths)->contains('/admin'));
        $this->assertFalse(collect($paths)->contains('/login'));
    }
}
