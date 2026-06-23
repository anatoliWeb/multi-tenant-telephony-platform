<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CommandsDocumentationTest extends TestCase
{
    public function test_commands_documentation_contains_daily_project_command_cookbook(): void
    {
        $path = base_path('docs/commands.md');
        $this->assertFileExists($path);

        $doc = (string) file_get_contents($path);
        $lower = strtolower($doc);

        $this->assertStringContainsString('# Commands', $doc);
        $this->assertStringContainsString('## Docker', $doc);
        $this->assertStringContainsString('## Backend / Laravel', $doc);
        $this->assertStringContainsString('## Backend Tests', $doc);
        $this->assertStringContainsString('## Vue Admin', $doc);
        $this->assertStringContainsString('## Angular Dashboard', $doc);
        $this->assertStringContainsString('## OpenAPI / Swagger', $doc);
        $this->assertStringContainsString('## Queue Workers', $doc);
        $this->assertStringContainsString('## Monitoring / Logs', $doc);
        $this->assertStringContainsString('## Cache / Redis', $doc);
        $this->assertStringContainsString('## Release / Deployment Checks', $doc);
        $this->assertStringContainsString('## Troubleshooting', $doc);

        $this->assertStringContainsString('docker compose up -d', $doc);
        $this->assertStringContainsString('composer test:openapi', $doc);
        $this->assertStringContainsString('npm run build', $doc);
        $this->assertStringContainsString('php artisan queue:failed', $doc);
        $this->assertStringContainsString('docker compose logs backend --tail=100', $doc);

        $this->assertStringContainsString('do not run multiple backend test processes in parallel', $lower);
        $this->assertStringContainsString('saas_testing', $doc);

        $this->assertStringNotContainsString('app_key=base64:', $lower);
        $this->assertStringNotContainsString('db_password=', $lower);
        $this->assertStringNotContainsString('redis_password=', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('ghp_', $lower);
        $this->assertStringNotContainsString('sk_live_', $lower);
    }
}

