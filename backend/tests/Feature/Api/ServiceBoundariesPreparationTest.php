<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ServiceBoundariesPreparationTest extends TestCase
{
    public function test_architecture_doc_contains_service_boundaries_foundation(): void
    {
        $docPath = base_path('docs/architecture.md');

        $this->assertFileExists($docPath);

        $doc = (string) file_get_contents($docPath);
        $docLower = strtolower($doc);

        $this->assertStringContainsString('## Service Boundaries', $doc);
        $this->assertStringContainsString('### Public Module Services', $doc);
        $this->assertStringContainsString('### Internal Module Services', $doc);
        $this->assertStringContainsString('### Infrastructure Services', $doc);
        $this->assertStringContainsString('### Allowed call patterns', $doc);
        $this->assertStringContainsString('### Avoid / forbidden call patterns', $doc);
        $this->assertStringContainsString('### Naming/marker convention', $doc);

        $this->assertStringContainsString('controller -> controller', $docLower);
        $this->assertStringContainsString('cross-module raw db writes', $docLower);

        $this->assertStringContainsString('ChatConversationService', $doc);
        $this->assertStringContainsString('ChatMessageService', $doc);
        $this->assertStringContainsString('ActivityService', $doc);
        $this->assertStringContainsString('MonitoringHealthService', $doc);
        $this->assertStringContainsString('ApiDocsPermissionService', $doc);

        $this->assertStringContainsString('no mass interface extraction', $docLower);
    }
}

