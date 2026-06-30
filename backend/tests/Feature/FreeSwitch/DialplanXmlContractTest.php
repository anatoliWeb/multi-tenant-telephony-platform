<?php

namespace Tests\Feature\FreeSwitch;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantStatus;
use App\Services\FreeSwitch\DialplanXmlBuilder;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\Support\FreeSwitchXmlAssertions;
use Tests\TestCase;

class DialplanXmlContractTest extends TestCase
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

    public function test_known_extension_destination_builds_parseable_dialplan_xml(): void
    {
        $tenant = $this->createTenant('dialplan-tenant-a');
        $otherTenant = $this->createTenant('dialplan-tenant-b');
        $owner = $this->actingAsTenantUser($this->createUser('dialplan-owner'));
        $otherOwner = $this->actingAsTenantUser($this->createUser('dialplan-other-owner'));

        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherOwner);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '3001',
            'label' => 'Routing Desk',
        ]);
        $otherExtension = $this->createExtensionFixture($otherTenant, $otherOwner, [
            'number' => '3999',
            'label' => 'Foreign Desk',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $xml = app(DialplanXmlBuilder::class)->buildForDestination('3001');
        $parsed = FreeSwitchXmlAssertions::parse($xml);

        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '/document/section[@name="dialplan"]/context[@name="default"]/extension', 'name', 'extension-3001');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//condition', 'field', 'destination_number');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//condition', 'expression', '^3001$');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//action[@application="bridge"]', 'data', 'user/3001@directory.contract.local');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, $otherExtension->label);
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, $otherExtension->number);
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, (string) $otherTenant->getKey());
    }

    public function test_unknown_destination_produces_safe_no_route_xml(): void
    {
        $tenant = $this->createTenant('dialplan-unknown');
        $owner = $this->actingAsTenantUser($this->createUser('dialplan-unknown-owner'));
        $this->createMembership($tenant, $owner);

        app(TenantContext::class)->setTenant($tenant);

        $xml = app(DialplanXmlBuilder::class)->buildForDestination('9999');
        $parsed = FreeSwitchXmlAssertions::parse($xml);

        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '/document/section[@name="dialplan"]/context[@name="default"]/extension', 'name', 'no-route');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//condition', 'expression', '^$');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, 'bridge');
    }

    public function test_cross_tenant_destination_is_not_routed(): void
    {
        $tenant = $this->createTenant('dialplan-cross-a');
        $otherTenant = $this->createTenant('dialplan-cross-b');
        $owner = $this->actingAsTenantUser($this->createUser('dialplan-cross-owner'));
        $otherOwner = $this->actingAsTenantUser($this->createUser('dialplan-cross-other-owner'));

        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherOwner);

        $otherExtension = $this->createExtensionFixture($otherTenant, $otherOwner, [
            'number' => '3010',
            'label' => 'Foreign Routing Desk',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $xml = app(DialplanXmlBuilder::class)->buildForDestination($otherExtension->number);
        $parsed = FreeSwitchXmlAssertions::parse($xml);

        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '/document/section[@name="dialplan"]/context[@name="default"]/extension', 'name', 'no-route');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, 'Foreign Routing Desk');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, $otherExtension->number);
    }

    public function test_disabled_tenant_is_not_routed(): void
    {
        $tenant = $this->createTenant('dialplan-disabled', TenantStatus::Suspended);
        $owner = $this->actingAsTenantUser($this->createUser('dialplan-disabled-owner'));
        $this->createMembership($tenant, $owner);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '3020',
            'status' => ExtensionStatus::Suspended->value,
            'label' => 'Suspended Routing Desk',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $xml = app(DialplanXmlBuilder::class)->buildForDestination($extension->number);
        $parsed = FreeSwitchXmlAssertions::parse($xml);

        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '/document/section[@name="dialplan"]/context[@name="default"]/extension', 'name', 'no-route');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, 'Suspended Routing Desk');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, $extension->number);
    }
}
