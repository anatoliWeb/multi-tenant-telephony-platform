<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class RealtimeServiceExtractionStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_realtime_service_extraction_strategy(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Realtime Service Extraction Strategy', $doc);
        $this->assertStringContainsString('Laravel Reverb/broadcasting', $doc);
        $this->assertStringContainsString('### Future Realtime Service Responsibilities', $doc);
        $this->assertStringContainsString('### What Realtime Service Should NOT Own', $doc);
        $this->assertStringContainsString('### Auth and Channel Authorization Model', $doc);
        $this->assertStringContainsString('### Event Input Model', $doc);
        $this->assertStringContainsString('### Data Ownership Model', $doc);
        $this->assertStringContainsString('### Migration Strategy', $doc);
        $this->assertStringContainsString('### Risks and Blockers', $doc);
        $this->assertStringContainsString('### Realtime Extraction Anti-Patterns', $doc);
        $this->assertStringContainsString('safe realtime/presence payload tests exist', strtolower($doc));
        $this->assertStringContainsString('not extracted in this phase', $docLower);
    }
}

