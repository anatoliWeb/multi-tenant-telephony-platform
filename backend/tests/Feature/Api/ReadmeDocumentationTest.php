<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ReadmeDocumentationTest extends TestCase
{
    private function repoRootPath(string $path = ''): string
    {
        $root = dirname(base_path());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    public function test_readme_contains_core_project_documentation_sections(): void
    {
        $readmePath = $this->repoRootPath('README.md');

        if (! file_exists($readmePath)) {
            $this->markTestSkipped('Repository root README.md is not mounted in this backend test container.');
        }

        $readme = (string) file_get_contents($readmePath);
        $lower = strtolower($readme);

        $this->assertStringContainsString('Multi-Tenant Telephony Platform', $readme);
        $this->assertStringContainsString('## Tech Stack', $readme);
        $this->assertStringContainsString('## Architecture Overview', $readme);
        $this->assertStringContainsString('backend/docs/architecture.md', $readme);
        $this->assertStringContainsString('backend/docs/api/openapi-preparation.md', $readme);
        $this->assertStringContainsString('## Local Development', $readme);
        $this->assertStringContainsString('## Testing', $readme);
        $this->assertStringContainsString('## Documentation Map', $readme);
        $this->assertStringContainsString('### Security', $readme);
        $this->assertStringContainsString('### Performance', $readme);
        $this->assertStringContainsString('### Monitoring & DevOps', $readme);

        $this->assertStringNotContainsString('app_key=base64:', $lower);
        $this->assertStringNotContainsString('db_password=', $lower);
        $this->assertStringNotContainsString('redis_password=', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);

        $this->assertStringContainsString('future strategy', $lower);
        $this->assertStringContainsString('modular monolith', $lower);
        $this->assertStringNotContainsString('microservices are implemented now', $lower);
    }
}
