<?php

namespace Tests\Feature\FreeSwitch;

use App\Services\FreeSwitch\DialplanXmlBuilder;
use App\Services\FreeSwitch\DirectoryXmlBuilder;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\Support\FreeSwitchXmlAssertions;
use Tests\TestCase;

class FreeSwitchProvisioningSecurityTest extends TestCase
{
    use BuildsExtensionFixtures;
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('freeswitch.directory_domain', 'directory.contract.local');
        app(TenantContext::class)->clear();
    }

    public function test_directory_and_dialplan_builders_do_not_log_sensitive_data(): void
    {
        $logger = new class implements LoggerInterface {
            /**
             * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
             */
            public array $records = [];

            public function emergency($message, array $context = []): void
            {
                $this->record('emergency', $message, $context);
            }

            public function alert($message, array $context = []): void
            {
                $this->record('alert', $message, $context);
            }

            public function critical($message, array $context = []): void
            {
                $this->record('critical', $message, $context);
            }

            public function error($message, array $context = []): void
            {
                $this->record('error', $message, $context);
            }

            public function warning($message, array $context = []): void
            {
                $this->record('warning', $message, $context);
            }

            public function notice($message, array $context = []): void
            {
                $this->record('notice', $message, $context);
            }

            public function info($message, array $context = []): void
            {
                $this->record('info', $message, $context);
            }

            public function debug($message, array $context = []): void
            {
                $this->record('debug', $message, $context);
            }

            public function log($level, $message, array $context = []): void
            {
                $this->record((string) $level, $message, $context);
            }

            private function record(string $level, mixed $message, array $context): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        Log::swap($logger);

        $tenant = $this->createTenant('security-tenant');
        $owner = $this->actingAsTenantUser($this->createUser('security-owner'));
        $this->createMembership($tenant, $owner);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '4001',
            'label' => 'Security Desk',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $directoryXml = app(DirectoryXmlBuilder::class)->build($extension);
        $dialplanXml = app(DialplanXmlBuilder::class)->buildForDestination($extension->number);

        $this->assertNotNull($directoryXml);
        $this->assertNotNull($dialplanXml);

        FreeSwitchXmlAssertions::assertDoesNotContain($directoryXml, 'credential_secret');
        FreeSwitchXmlAssertions::assertDoesNotContain($directoryXml, 'secret_hint');
        FreeSwitchXmlAssertions::assertDoesNotContain($dialplanXml, 'credential_secret');
        FreeSwitchXmlAssertions::assertDoesNotContain($dialplanXml, 'secret_hint');

        $this->assertSame([], $logger->records);
    }
}
