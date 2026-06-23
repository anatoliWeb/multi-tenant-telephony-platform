<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ArchitectureDocumentationTest extends TestCase
{
    public function test_architecture_doc_contains_central_architecture_map_sections(): void
    {
        $path = base_path('docs/architecture.md');
        $this->assertFileExists($path);

        $doc = (string) file_get_contents($path);
        $lower = strtolower($doc);

        $this->assertStringContainsString('# Architecture', $doc);
        $this->assertStringContainsString('## Purpose', $doc);
        $this->assertStringContainsString('## Architectural Style', $doc);
        $this->assertStringContainsString('## System Context', $doc);
        $this->assertStringContainsString('## Runtime Building Blocks', $doc);
        $this->assertStringContainsString('## Cross-Cutting Architecture Foundations', $doc);
        $this->assertStringContainsString('## Documentation Map', $doc);

        $this->assertStringContainsString('```mermaid', $doc);
        $this->assertStringContainsString('Nginx', $doc);
        $this->assertStringContainsString('Laravel API', $doc);
        $this->assertStringContainsString('MySQL', $doc);
        $this->assertStringContainsString('Redis', $doc);
        $this->assertStringContainsString('Reverb', $doc);
        $this->assertStringContainsString('Queue Workers', $doc);
        $this->assertStringContainsString('Vue Admin', $doc);
        $this->assertStringContainsString('Angular Dashboard', $doc);

        $this->assertStringContainsString('## Internal Module Contracts', $doc);
        $this->assertStringContainsString('## Event-Driven Module Communication', $doc);
        $this->assertStringContainsString('## Service Boundaries', $doc);
        $this->assertStringContainsString('## Future Extraction Strategy', $doc);

        $this->assertStringContainsString('backend/docs/microservices.md', $doc);
        $this->assertStringContainsString('backend/docs/security.md', $doc);
        $this->assertStringContainsString('backend/docs/performance.md', $doc);
        $this->assertStringContainsString('backend/docs/monitoring.md', $doc);
        $this->assertStringContainsString('backend/docs/deployment.md', $doc);

        $this->assertStringContainsString('modular monolith', $lower);
        $this->assertStringContainsString('future strategy', $lower);
    }
}

