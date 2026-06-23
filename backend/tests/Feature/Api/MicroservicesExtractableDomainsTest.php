<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MicroservicesExtractableDomainsTest extends TestCase
{
    public function test_microservices_doc_contains_extractable_domains_foundation(): void
    {
        $docPath = base_path('docs/microservices.md');

        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Extractable Domains', $doc);
        $this->assertStringContainsString('### Notifications', $doc);
        $this->assertStringContainsString('### Realtime/WebSocket', $doc);
        $this->assertStringContainsString('### External Webhooks', $doc);
        $this->assertStringContainsString('### Activity/Audit', $doc);
        $this->assertStringContainsString('### Auth/Identity', $doc);
        $this->assertStringContainsString('### Chat', $doc);

        $this->assertStringContainsString('## Extraction Decision Matrix', $doc);
        $this->assertStringContainsString('Readiness level', $doc);
        $this->assertStringContainsString('Current dependencies', $doc);
        $this->assertStringContainsString('Current data ownership', $doc);
        $this->assertStringContainsString('Extraction blockers', $doc);

        $this->assertStringContainsString('shared database microservices', $docLower);
        $this->assertStringContainsString('distributed transactions', $docLower);
        $this->assertStringContainsString('cross-service db writes', $docLower);
        $this->assertStringContainsString('modular monolith remains the current architecture strategy', $docLower);
        $this->assertStringContainsString('no microservice extraction is performed in this phase', $docLower);
    }
}

