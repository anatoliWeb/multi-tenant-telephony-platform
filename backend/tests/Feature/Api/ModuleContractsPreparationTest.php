<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ModuleContractsPreparationTest extends TestCase
{
    public function test_architecture_doc_contains_internal_module_contracts_foundation(): void
    {
        $docPath = base_path('docs/architecture.md');

        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);

        $this->assertStringContainsString('## Internal Module Contracts', $doc);
        $this->assertStringContainsString('### Auth / Identity Module', $doc);
        $this->assertStringContainsString('### Users / RBAC Module', $doc);
        $this->assertStringContainsString('### Chat Module', $doc);
        $this->assertStringContainsString('### Notifications Module', $doc);
        $this->assertStringContainsString('### Activity Module', $doc);
        $this->assertStringContainsString('### Realtime Module', $doc);
        $this->assertStringContainsString('### Webhooks / External API Module', $doc);
        $this->assertStringContainsString('### Monitoring Module', $doc);

        $this->assertStringContainsString('Public services/contracts', $doc);
        $this->assertStringContainsString('Allowed dependencies', $doc);
        $this->assertStringContainsString('Forbidden dependencies', $doc);
        $this->assertStringContainsString('Data ownership', $doc);
        $this->assertStringContainsString('Extraction readiness notes', $doc);

        // Foundation should avoid premature extraction language.
        $this->assertStringNotContainsString('microservice now', strtolower($doc));
        $this->assertStringNotContainsString('extract immediately', strtolower($doc));
    }
}

