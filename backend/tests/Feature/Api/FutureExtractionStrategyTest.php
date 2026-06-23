<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class FutureExtractionStrategyTest extends TestCase
{
    public function test_architecture_doc_contains_future_extraction_strategy_foundation(): void
    {
        $docPath = base_path('docs/architecture.md');

        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Future Extraction Strategy', $doc);
        $this->assertStringContainsString('### Extraction readiness levels', $doc);

        $this->assertStringContainsString('Notifications candidate', $doc);
        $this->assertStringContainsString('Realtime/WebSocket candidate', $doc);
        $this->assertStringContainsString('External Webhooks candidate', $doc);
        $this->assertStringContainsString('Activity/Audit candidate', $doc);
        $this->assertStringContainsString('Auth/Identity candidate', $doc);
        $this->assertStringContainsString('Chat candidate (complex)', $doc);

        $this->assertStringContainsString('Required contracts before extraction', $doc);
        $this->assertStringContainsString('Data ownership changes before extraction', $doc);
        $this->assertStringContainsString('Communication style after extraction', $doc);
        $this->assertStringContainsString('Risks:', $doc);
        $this->assertStringContainsString('Not-now reason:', $doc);

        $this->assertStringContainsString('shared database microservices', $docLower);
        $this->assertStringContainsString('direct cross-service db writes', $docLower);
        $this->assertStringContainsString('distributed transactions', $docLower);
        $this->assertStringContainsString('modular monolith', $docLower);
    }
}

