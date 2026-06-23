<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ReleaseWorkflowPreparationTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_release_docs_cover_versioning_validation_and_rollback(): void
    {
        $releaseDocPath = base_path('docs/release.md');
        $ciDocPath = base_path('docs/ci-cd.md');
        $deploymentDocPath = base_path('docs/deployment.md');

        $this->assertFileExists($releaseDocPath);
        $this->assertFileExists($ciDocPath);
        $this->assertFileExists($deploymentDocPath);

        $releaseDoc = (string) file_get_contents($releaseDocPath);
        $ciDoc = (string) file_get_contents($ciDocPath);
        $deploymentDoc = (string) file_get_contents($deploymentDocPath);

        $this->assertStringContainsString('## Versioning Strategy', $releaseDoc);
        $this->assertStringContainsString('Semantic Versioning', $releaseDoc);
        $this->assertStringContainsString('git tag -a v0.1.0', $releaseDoc);
        $this->assertStringContainsString('## Validation Commands', $releaseDoc);
        $this->assertStringContainsString('composer test:openapi', $releaseDoc);
        $this->assertStringContainsString('docker compose config', $releaseDoc);
        $this->assertStringContainsString('## Database Migrations', $releaseDoc);
        $this->assertStringContainsString('## Rollback Strategy', $releaseDoc);
        $this->assertStringContainsString('## Post-release Verification', $releaseDoc);

        // Sensitive values must not be present in release docs.
        $this->assertStringNotContainsString('sk_live_', $releaseDoc);
        $this->assertStringNotContainsString('ghp_', $releaseDoc);
        $this->assertStringNotContainsString('AKIA', $releaseDoc);

        // Cross-links present.
        $this->assertStringContainsString('backend/docs/release.md', $ciDoc);
        $this->assertStringContainsString('backend/docs/release.md', $deploymentDoc);
    }

    public function test_ci_workflow_covers_release_readiness_without_deploy(): void
    {
        $ciWorkflowPath = $this->repoRootPath('.github/workflows/ci.yml');

        if (! file_exists($ciWorkflowPath)) {
            $this->markTestSkipped('Repository root workflow files are not mounted in this backend test container.');
        }

        $workflow = (string) file_get_contents($ciWorkflowPath);

        $this->assertStringContainsString('backend-tests:', $workflow);
        $this->assertStringContainsString('docker-config:', $workflow);
        $this->assertStringContainsString('composer test:openapi', $workflow);

        // No deployment stage in this foundation step.
        $this->assertStringNotContainsString('kubectl', $workflow);
        $this->assertStringNotContainsString('helm', $workflow);
        $this->assertStringNotContainsString('docker push', $workflow);
    }
}

