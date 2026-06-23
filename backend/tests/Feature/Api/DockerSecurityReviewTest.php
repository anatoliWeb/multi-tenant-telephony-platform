<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DockerSecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());
        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_docker_static_security_baseline(): void
    {
        $composePath = $this->repoRootPath('docker-compose.yml');
        $dockerignorePath = $this->repoRootPath('.dockerignore');
        $phpDockerfilePath = $this->repoRootPath('docker/php/Dockerfile');
        $frontendDockerfilePath = $this->repoRootPath('docker/frontend/Dockerfile');
        $nginxConfPath = $this->repoRootPath('docker/nginx/default.conf');
        $entrypointPath = base_path('docker/entrypoint.sh');
        $queueEntrypointPath = base_path('docker/queue/entrypoint.sh');
        $securityDocPath = base_path('docs/security.md');

        $repoLevelPaths = [
            $composePath,
            $dockerignorePath,
            $phpDockerfilePath,
            $frontendDockerfilePath,
            $nginxConfPath,
        ];
        $repoLevelAvailable = collect($repoLevelPaths)->every(static fn (string $path): bool => file_exists($path));

        foreach ([
            $entrypointPath,
            $queueEntrypointPath,
            $securityDocPath,
        ] as $requiredPath) {
            $this->assertFileExists($requiredPath, "Expected file missing: {$requiredPath}");
        }

        if (! $repoLevelAvailable) {
            $this->markTestSkipped('Repository root Docker files are not mounted in this backend test container; run this test in a full-repo test context for complete static checks.');
        }

        $compose = (string) file_get_contents($composePath);
        $dockerignore = (string) file_get_contents($dockerignorePath);
        $phpDockerfile = (string) file_get_contents($phpDockerfilePath);
        $frontendDockerfile = (string) file_get_contents($frontendDockerfilePath);
        $nginxConf = (string) file_get_contents($nginxConfPath);
        $entrypoint = (string) file_get_contents($entrypointPath);
        $queueEntrypoint = (string) file_get_contents($queueEntrypointPath);
        $securityDoc = (string) file_get_contents($securityDocPath);

        // 1) Compose should not contain obvious real-looking secrets.
        $this->assertStringNotContainsString('sk_live_', $compose);
        $this->assertStringNotContainsString('ghp_', $compose);
        $this->assertStringNotContainsString('AKIA', $compose);

        // 2) Dockerfiles must not copy .env into image layers.
        $this->assertDoesNotMatchRegularExpression('/copy\s+.*\.env/i', $phpDockerfile);
        $this->assertDoesNotMatchRegularExpression('/copy\s+.*\.env/i', $frontendDockerfile);

        // 3) .dockerignore excludes env files.
        $this->assertMatchesRegularExpression('/^\.env$/m', $dockerignore);
        $this->assertMatchesRegularExpression('/^\.env\.\*$/m', $dockerignore);

        // 4) nginx denies hidden files and does not allow directory listing.
        $this->assertStringContainsString('location ~ /\\.(?!well-known).*', $nginxConf);
        $this->assertStringContainsString('deny all;', $nginxConf);
        $this->assertStringContainsString('autoindex off;', $nginxConf);

        // 5) Compose includes healthchecks for core dependencies/services.
        $this->assertMatchesRegularExpression('/backend:.*?healthcheck:/si', $compose);
        $this->assertMatchesRegularExpression('/mysql:.*?healthcheck:/si', $compose);
        $this->assertMatchesRegularExpression('/redis:.*?healthcheck:/si', $compose);

        // 6) Entrypoints should not echo secrets directly.
        $this->assertDoesNotMatchRegularExpression('/echo\s+.*(password|token|secret|app_key)/i', $entrypoint);
        $this->assertDoesNotMatchRegularExpression('/echo\s+.*(password|token|secret|app_key)/i', $queueEntrypoint);

        // 7) Security docs include Docker security review guidance.
        $this->assertStringContainsString('## Docker Security Review', $securityDoc);
        $this->assertStringContainsString('Dev vs production assumptions', $securityDoc);
        $this->assertStringContainsString('Nginx hardening baseline', $securityDoc);
    }
}
