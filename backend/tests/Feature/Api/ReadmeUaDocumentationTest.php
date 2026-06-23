<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ReadmeUaDocumentationTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_readme_ua_contains_core_project_documentation_sections(): void
    {
        $readmePath = $this->repoRootPath('README_UA.md');

        if (! file_exists($readmePath)) {
            $this->markTestSkipped('Repository root README_UA.md is not mounted in this backend test container.');
        }

        $readme = (string) file_get_contents($readmePath);
        $lower = mb_strtolower($readme);

        $this->assertStringContainsString('Multi-Tenant Telephony Platform', $readme);
        $this->assertStringContainsString('## Технологічний стек', $readme);
        $this->assertStringContainsString('## Огляд архітектури', $readme);
        $this->assertStringContainsString('## Локальний запуск', $readme);
        $this->assertStringContainsString('## Тестування', $readme);
        $this->assertStringContainsString('## Карта документації', $readme);
        $this->assertStringContainsString('### Security', $readme);
        $this->assertStringContainsString('### Performance', $readme);
        $this->assertStringContainsString('### Monitoring & DevOps', $readme);

        $this->assertStringContainsString('backend/docs/architecture.md', $readme);
        $this->assertStringContainsString('backend/docs/security.md', $readme);
        $this->assertStringContainsString('backend/docs/deployment.md', $readme);

        $this->assertStringNotContainsString('app_key=base64:', $lower);
        $this->assertStringNotContainsString('db_password=', $lower);
        $this->assertStringNotContainsString('redis_password=', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);

        $this->assertStringContainsString('future strategy', $lower);
        $this->assertStringContainsString('modular monolith', $lower);
        $this->assertStringNotContainsString('microservices are implemented now', $lower);
    }
}
