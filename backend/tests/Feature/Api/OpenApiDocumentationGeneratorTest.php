<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiDocumentationGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_generator_dependency_and_config_exist(): void
    {
        $composerRaw = file_get_contents(base_path('composer.json'));
        $this->assertIsString($composerRaw);
        $composer = json_decode($composerRaw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('dedoc/scramble', (array) data_get($composer, 'require', []));

        $this->assertNotNull(config('scramble.api_path'));
        $this->assertSame('api/v1', config('scramble.api_path'));
        $this->assertNotEmpty((array) config('scramble.middleware', []));
    }

    public function test_docs_ui_and_json_routes_are_available_and_valid(): void
    {
        $uiResponse = $this->get('/docs/api')
            ->assertOk();
        $this->assertStringContainsStringIgnoringCase('<html', $uiResponse->getContent());

        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $this->assertNotEmpty(data_get($spec, 'openapi'));
        $this->assertNotEmpty(data_get($spec, 'info'));
        $this->assertNotEmpty(data_get($spec, 'paths'));
        $this->assertNotEmpty(data_get($spec, 'components'));
    }

    public function test_generator_docs_and_contract_test_files_exist_with_expected_content(): void
    {
        $generatorDocPath = base_path('docs/api/openapi-generator.md');
        $this->assertFileExists($generatorDocPath);
        $generatorDoc = file_get_contents($generatorDocPath);
        $this->assertIsString($generatorDoc);
        $this->assertStringContainsString('dedoc/scramble', $generatorDoc);
        $this->assertStringContainsString('/docs/api', $generatorDoc);
        $this->assertStringContainsString('/docs/api.json', $generatorDoc);
        $this->assertStringContainsString('test:openapi', $generatorDoc);

        $this->assertFileExists(base_path('tests/Feature/Api/OpenApiRouteContractTest.php'));
    }

    public function test_spec_excludes_internal_routes_and_sensitive_strings(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $paths = array_keys((array) data_get($spec, 'paths', []));
        $serialized = strtolower((string) json_encode($spec, JSON_THROW_ON_ERROR));

        $this->assertFalse(collect($paths)->contains('/broadcasting/auth'));
        $this->assertFalse(collect($paths)->contains(fn (string $path): bool => str_starts_with($path, '/admin')));

        foreach ([
            'token_hash',
            'webhook_secret',
            'raw_payload',
            'raw_response',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized);
        }
    }
}

