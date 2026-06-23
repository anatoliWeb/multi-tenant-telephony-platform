<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ProductionConfigTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_production_configuration_foundation_exists(): void
    {
        $prodEnvPath = base_path('.env.production.example');
        $deploymentDocPath = base_path('docs/deployment.md');

        $this->assertFileExists($prodEnvPath);
        $this->assertFileExists($deploymentDocPath);

        $prodEnv = (string) file_get_contents($prodEnvPath);
        $deploymentDoc = (string) file_get_contents($deploymentDocPath);

        $this->assertStringContainsString('APP_ENV=production', $prodEnv);
        $this->assertStringContainsString('APP_DEBUG=false', $prodEnv);
        $this->assertStringContainsString('CACHE_STORE=redis', $prodEnv);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $prodEnv);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $prodEnv);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $prodEnv);
        $this->assertStringContainsString('API_DOCS_LOCAL_BYPASS=false', $prodEnv);
        $this->assertStringContainsString('SECURITY_HSTS_ENABLED=true', $prodEnv);

        // Keep obvious real token formats out of templates.
        $this->assertStringNotContainsString('sk_live_', $prodEnv);
        $this->assertStringNotContainsString('ghp_', $prodEnv);
        $this->assertStringNotContainsString('AKIA', $prodEnv);

        $this->assertDocumentationContainsAnyHeading($deploymentDoc, [
            '## Production Configuration',
            '## Production Environment Variables',
        ]);
        $this->assertDocumentationContainsAnyHeading($deploymentDoc, [
            '## Security Defaults',
            '## Security Checklist',
        ]);
        $this->assertDocumentationContainsAnyHeading($deploymentDoc, [
            '## Cache/Queue',
            '## Queue Workers',
        ]);
        $this->assertStringContainsString('## API Docs Access', $deploymentDoc);
        $this->assertDocumentationContainsAnyHeading($deploymentDoc, [
            '## Reverb/WebSockets',
            '## Reverb / Realtime',
        ]);
        $this->assertStringContainsString('## Production Checklist', $deploymentDoc);
    }

    public function test_production_compose_template_is_safe_if_present(): void
    {
        $composeProdPath = $this->repoRootPath('docker-compose.prod.example.yml');
        $composeDevPath = $this->repoRootPath('docker-compose.yml');

        if (! file_exists($composeProdPath) || ! file_exists($composeDevPath)) {
            $this->markTestSkipped('Repository root compose templates are not mounted in this backend test container.');
        }

        $composeProd = (string) file_get_contents($composeProdPath);

        // Ensure template does not expose DB/Redis public ports by default.
        $this->assertDoesNotMatchRegularExpression('/mysql:.*?ports:/si', $composeProd);
        $this->assertDoesNotMatchRegularExpression('/redis:.*?ports:/si', $composeProd);
        $this->assertStringContainsString('backend/.env.production', $composeProd);
        $this->assertStringContainsString('restart: unless-stopped', $composeProd);
    }

    /**
     * @param array<int, string> $headings
     */
    private function assertDocumentationContainsAnyHeading(string $contents, array $headings): void
    {
        foreach ($headings as $heading) {
            if (str_contains($contents, $heading)) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail('Expected documentation to contain one of: '.implode(', ', $headings));
    }
}
