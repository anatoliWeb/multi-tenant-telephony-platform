<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class NotificationServiceExtractionStrategyTest extends TestCase
{
    public function test_microservices_doc_contains_notification_service_extraction_strategy(): void
    {
        $docPath = base_path('docs/microservices.md');
        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Notification Service Extraction Strategy', $doc);
        $this->assertStringContainsString('### Current State', $doc);
        $this->assertStringContainsString('### Future Notification Service Responsibilities', $doc);
        $this->assertStringContainsString('### What Notification Service Should NOT Own', $doc);
        $this->assertStringContainsString('### Data Ownership Model (Future)', $doc);
        $this->assertStringContainsString('### Contracts Required Before Extraction', $doc);
        $this->assertStringContainsString('#### API contracts', $doc);
        $this->assertStringContainsString('#### Event contracts', $doc);
        $this->assertStringContainsString('### Async Communication Model', $doc);
        $this->assertStringContainsString('### Migration Strategy', $doc);
        $this->assertStringContainsString('### Risks and Blockers', $doc);
        $this->assertStringContainsString('### Notification Extraction Anti-Patterns', $doc);

        $this->assertStringContainsString('not extracted in this phase', $docLower);
    }
}

