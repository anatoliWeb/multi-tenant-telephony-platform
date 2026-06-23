<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CiCdPreparationTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_ci_cd_foundation_files_and_policy_exist(): void
    {
        $workflowPath = $this->repoRootPath('.github/workflows/ci.yml');
        $ciDocPath = base_path('docs/ci-cd.md');

        $this->assertFileExists($ciDocPath);

        if (! file_exists($workflowPath)) {
            $this->markTestSkipped('Repository root workflow files are not mounted in this backend test container.');
        }

        $workflow = (string) file_get_contents($workflowPath);
        $ciDoc = (string) file_get_contents($ciDocPath);

        $this->assertStringContainsString('backend-tests:', $workflow);
        $this->assertStringContainsString('backend-frontend:', $workflow);
        $this->assertStringContainsString('angular-frontend:', $workflow);
        $this->assertStringContainsString('docker-config:', $workflow);
        $this->assertStringContainsString('composer test:openapi', $workflow);
        $this->assertStringContainsString('php -d memory_limit=512M artisan test --filter=Api --stop-on-failure', $workflow);
        $this->assertStringContainsString('npm test', $workflow);
        $this->assertStringContainsString('npm run build', $workflow);
        $this->assertStringContainsString('docker compose config', $workflow);
        $this->assertStringContainsString('docker compose -f docker-compose.prod.example.yml config', $workflow);

        // Ensure there is no hardcoded deployment command in this foundation step.
        $this->assertStringNotContainsString('kubectl apply', $workflow);
        $this->assertStringNotContainsString('helm upgrade', $workflow);
        $this->assertStringNotContainsString('docker login', $workflow);
        $this->assertStringNotContainsString('sk_live_', $workflow);
        $this->assertStringNotContainsString('ghp_', $workflow);
        $this->assertStringNotContainsString('AKIA', $workflow);

        $this->assertStringContainsString('## CI/CD Preparation', $ciDoc);
        $this->assertStringContainsString('## What CI checks', $ciDoc);
        $this->assertStringContainsString('## What CI does not do', $ciDoc);
        $this->assertStringContainsString('## Secrets policy', $ciDoc);
    }
}

