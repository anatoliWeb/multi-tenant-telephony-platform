<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DeploymentDocumentationTest extends TestCase
{
    public function test_deployment_documentation_contains_production_preparation_guide(): void
    {
        $path = base_path('docs/deployment.md');
        $this->assertFileExists($path);

        $doc = (string) file_get_contents($path);
        $lower = strtolower($doc);

        $this->assertStringContainsString('# Deployment', $doc);
        $this->assertStringContainsString('## Purpose', $doc);
        $this->assertStringContainsString('## Deployment Model', $doc);
        $this->assertStringContainsString('## Environments', $doc);
        $this->assertStringContainsString('## Production Environment Variables', $doc);
        $this->assertStringContainsString('## Docker Production Template', $doc);
        $this->assertStringContainsString('## Build and Release Flow', $doc);
        $this->assertStringContainsString('## Database Migrations', $doc);
        $this->assertStringContainsString('## Queue Workers', $doc);
        $this->assertStringContainsString('## Reverb / Realtime', $doc);
        $this->assertStringContainsString('## API Docs Access', $doc);
        $this->assertStringContainsString('## Health Checks', $doc);
        $this->assertStringContainsString('## Security Checklist', $doc);
        $this->assertStringContainsString('## Rollback Strategy', $doc);
        $this->assertStringContainsString('## Production Checklist', $doc);

        $this->assertStringContainsString('backend/.env.production.example', $doc);
        $this->assertStringContainsString('docker-compose.prod.example.yml', $doc);
        $this->assertStringContainsString('APP_DEBUG=false', $doc);
        $this->assertStringContainsString('CACHE_STORE=redis', $doc);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $doc);
        $this->assertStringContainsString('API_DOCS_LOCAL_BYPASS=false', $doc);

        $this->assertStringContainsString('backend/docs/commands.md', $doc);
        $this->assertStringContainsString('backend/docs/docker.md', $doc);
        $this->assertStringContainsString('backend/docs/release.md', $doc);
        $this->assertStringContainsString('backend/docs/ci-cd.md', $doc);
        $this->assertStringContainsString('backend/docs/security.md', $doc);
        $this->assertStringContainsString('backend/docs/monitoring.md', $doc);

        $this->assertStringNotContainsString('sk_live_', $lower);
        $this->assertStringNotContainsString('ghp_', $lower);
        $this->assertStringNotContainsString('akia', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('db_password=', $lower);
        $this->assertStringNotContainsString('redis_password=', $lower);

        $this->assertStringContainsString('provider-neutral', $lower);
        $this->assertStringContainsString('does not define a specific cloud platform', $lower);
        $this->assertStringNotContainsString('kubectl apply', $lower);
        $this->assertStringNotContainsString('helm upgrade', $lower);
        $this->assertStringNotContainsString('terraform apply', $lower);
    }
}

