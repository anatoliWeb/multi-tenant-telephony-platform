<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DockerDocumentationTest extends TestCase
{
    public function test_docker_documentation_contains_services_build_env_and_operations_guide(): void
    {
        $path = base_path('docs/docker.md');
        $this->assertFileExists($path);

        $doc = (string) file_get_contents($path);
        $lower = strtolower($doc);

        $this->assertStringContainsString('# Docker', $doc);
        $this->assertStringContainsString('## Purpose', $doc);
        $this->assertStringContainsString('## Compose Files', $doc);
        $this->assertStringContainsString('## Services', $doc);
        $this->assertStringContainsString('## Images and Build Strategy', $doc);
        $this->assertStringContainsString('## Environment Files', $doc);
        $this->assertStringContainsString('## Volumes and Persistence', $doc);
        $this->assertStringContainsString('## Ports and Networking', $doc);
        $this->assertStringContainsString('## Healthchecks', $doc);
        $this->assertStringContainsString('## Logs', $doc);
        $this->assertStringContainsString('## Security Notes', $doc);
        $this->assertStringContainsString('## Common Commands', $doc);
        $this->assertStringContainsString('## Troubleshooting', $doc);

        $this->assertStringContainsString('docker-compose.yml', $doc);
        $this->assertStringContainsString('docker-compose.prod.example.yml', $doc);
        $this->assertStringContainsString('.dockerignore', $doc);
        $this->assertStringContainsString('Dockerfiles must not copy `.env` into images', $doc);

        foreach ([
            'backend',
            'nginx',
            'mysql',
            'redis',
            'queue-worker',
            'reverb',
            'horizon',
            'frontend',
            'vue-frontend',
        ] as $service) {
            $this->assertStringContainsString($service, $doc);
        }

        $this->assertStringContainsString('backend/docs/commands.md', $doc);
        $this->assertStringContainsString('backend/docs/deployment.md', $doc);
        $this->assertStringContainsString('backend/docs/security.md', $doc);
        $this->assertStringContainsString('backend/docs/monitoring.md', $doc);
        $this->assertStringContainsString('backend/docs/performance.md', $doc);

        $this->assertStringNotContainsString('sk_live_', $lower);
        $this->assertStringNotContainsString('ghp_', $lower);
        $this->assertStringNotContainsString('akia', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('db_password=', $lower);
        $this->assertStringNotContainsString('redis_password=', $lower);

        $this->assertStringContainsString('not a provider-specific deployment runbook', $lower);
        $this->assertStringContainsString('production template', $lower);
        $this->assertStringNotContainsString('production deployment is fully implemented', $lower);
    }
}

