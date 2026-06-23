<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContainerLogStrategyTest extends TestCase
{
    use RefreshDatabase;

    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_container_log_strategy_static_baseline(): void
    {
        $composePath = $this->repoRootPath('docker-compose.yml');
        $nginxConfPath = $this->repoRootPath('docker/nginx/default.conf');
        $supervisorPath = $this->repoRootPath('docker/supervisor/supervisord.conf');
        $envExamplePath = base_path('.env.example');
        $monitoringDocPath = base_path('docs/monitoring.md');
        $entrypointPath = base_path('docker/entrypoint.sh');
        $queueEntrypointPath = base_path('docker/queue/entrypoint.sh');

        foreach ([
            $envExamplePath,
            $monitoringDocPath,
            $entrypointPath,
            $queueEntrypointPath,
        ] as $requiredPath) {
            $this->assertFileExists($requiredPath, "Expected file missing: {$requiredPath}");
        }

        $repoLevelPaths = [
            $composePath,
            $nginxConfPath,
            $supervisorPath,
        ];
        $repoLevelAvailable = collect($repoLevelPaths)->every(static fn (string $path): bool => file_exists($path));

        if (! $repoLevelAvailable) {
            $this->markTestSkipped('Repository root Docker files are not mounted in this backend test container; run this test in a full-repo test context for complete static checks.');
        }

        $compose = (string) file_get_contents($composePath);
        $nginxConf = (string) file_get_contents($nginxConfPath);
        $supervisor = (string) file_get_contents($supervisorPath);
        $envExample = (string) file_get_contents($envExamplePath);
        $monitoringDoc = (string) file_get_contents($monitoringDocPath);
        $entrypoint = (string) file_get_contents($entrypointPath);
        $queueEntrypoint = (string) file_get_contents($queueEntrypointPath);

        // Compose logging options baseline.
        $this->assertStringContainsString('x-logging:', $compose);
        $this->assertStringContainsString('driver: json-file', $compose);
        $this->assertStringContainsString('max-size: "10m"', $compose);
        $this->assertStringContainsString('max-file: "3"', $compose);

        // Nginx stdout/stderr strategy.
        $this->assertStringContainsString('access_log /dev/stdout;', $nginxConf);
        $this->assertStringContainsString('error_log /dev/stderr warn;', $nginxConf);

        // Supervisor stdout/stderr strategy.
        $this->assertStringContainsString('stdout_logfile=/dev/stdout', $supervisor);
        $this->assertStringContainsString('stderr_logfile=/dev/stderr', $supervisor);

        // Env docs include logging knobs.
        $this->assertStringContainsString('LOG_CHANNEL=', $envExample);
        $this->assertStringContainsString('LOG_LEVEL=', $envExample);
        $this->assertStringContainsString('LOG_STACK=', $envExample);

        // Monitoring docs include container strategy.
        $this->assertStringContainsString('## Container Log Strategy', $monitoringDoc);
        $this->assertStringContainsString('docker compose logs backend --tail=100', $monitoringDoc);
        $this->assertStringContainsString('docker compose logs queue-worker --tail=100', $monitoringDoc);
        $this->assertStringContainsString('docker compose logs nginx --tail=100', $monitoringDoc);

        // Entrypoints must not echo sensitive values.
        $this->assertDoesNotMatchRegularExpression('/echo\s+.*(password|token|secret|authorization|app_key)/i', $entrypoint);
        $this->assertDoesNotMatchRegularExpression('/echo\s+.*(password|token|secret|authorization|app_key)/i', $queueEntrypoint);
    }
}

