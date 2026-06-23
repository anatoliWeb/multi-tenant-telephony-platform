<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DockerImageOptimizationTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_docker_image_optimization_static_baseline(): void
    {
        $dockerignorePath = $this->repoRootPath('.dockerignore');
        $composePath = $this->repoRootPath('docker-compose.yml');
        $phpDockerfilePath = $this->repoRootPath('docker/php/Dockerfile');
        $frontendDockerfilePath = $this->repoRootPath('docker/frontend/Dockerfile');
        $docsPath = base_path('docs/docker.md');

        $repoLevelPaths = [
            $dockerignorePath,
            $composePath,
            $phpDockerfilePath,
            $frontendDockerfilePath,
        ];
        $repoLevelAvailable = collect($repoLevelPaths)->every(static fn (string $path): bool => file_exists($path));

        $this->assertFileExists($docsPath, "Expected file missing: {$docsPath}");

        if (! $repoLevelAvailable) {
            $this->markTestSkipped('Repository root Docker files are not mounted in this backend test container; run this test in a full-repo test context for complete static checks.');
        }

        $dockerignore = (string) file_get_contents($dockerignorePath);
        $compose = (string) file_get_contents($composePath);
        $phpDockerfile = (string) file_get_contents($phpDockerfilePath);
        $frontendDockerfile = (string) file_get_contents($frontendDockerfilePath);
        $docs = (string) file_get_contents($docsPath);

        // .dockerignore baseline for bloat/safety.
        $this->assertMatchesRegularExpression('/^\.env$/m', $dockerignore);
        $this->assertMatchesRegularExpression('/^\.env\.\*$/m', $dockerignore);
        $this->assertStringContainsString('**/node_modules', $dockerignore);
        $this->assertStringContainsString('**/vendor', $dockerignore);
        $this->assertStringContainsString('**/storage/logs', $dockerignore);
        $this->assertMatchesRegularExpression('/^\.git$/m', $dockerignore);

        // Dockerfiles should not copy .env.
        $this->assertDoesNotMatchRegularExpression('/copy\s+.*\.env/i', $phpDockerfile);
        $this->assertDoesNotMatchRegularExpression('/copy\s+.*\.env/i', $frontendDockerfile);

        // PHP Dockerfile keeps package cache clean and uses safer composer source pinning.
        $this->assertStringContainsString('--no-install-recommends', $phpDockerfile);
        $this->assertStringContainsString('apt-get clean', $phpDockerfile);
        $this->assertStringContainsString('rm -rf /var/lib/apt/lists/*', $phpDockerfile);
        $this->assertStringContainsString('COPY --from=composer:2', $phpDockerfile);

        // Compose should continue reusing backend image for worker-style services.
        $this->assertStringContainsString('queue-worker:', $compose);
        $this->assertMatchesRegularExpression('/queue-worker:.*?image:\s+\$\{APP_NAME:-multi-tenant-telephony-platform\}-backend:latest/si', $compose);

        // Docs should describe this optimization strategy.
        $this->assertStringContainsString('## Docker Image Optimization', $docs);
        $this->assertStringContainsString('### .dockerignore policy', $docs);
        $this->assertStringContainsString('### Layer cache strategy', $docs);
        $this->assertStringContainsString('### Dev vs production assumptions', $docs);
    }
}
