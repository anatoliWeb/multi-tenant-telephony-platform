<?php

namespace Tests\Feature\FreeSwitch;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantStatus;
use App\Services\FreeSwitch\DirectoryXmlBuilder;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\Support\FreeSwitchXmlAssertions;
use Tests\TestCase;

class DirectoryXmlContractTest extends TestCase
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

    public function test_active_tenant_extension_builds_parseable_directory_xml(): void
    {
        $tenant = $this->createTenant('directory-tenant-a');
        $otherTenant = $this->createTenant('directory-tenant-b');
        $owner = $this->actingAsTenantUser($this->createUser('directory-owner'));
        $otherOwner = $this->actingAsTenantUser($this->createUser('directory-other-owner'));

        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherOwner);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '2001',
            'label' => 'Reception Desk',
        ]);
        $otherExtension = $this->createExtensionFixture($otherTenant, $otherOwner, [
            'number' => '2999',
            'label' => 'Foreign Desk',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $xml = app(DirectoryXmlBuilder::class)->build($extension);

        $this->assertNotNull($xml);

        $parsed = FreeSwitchXmlAssertions::parse($xml);
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '/document/section[@name="directory"]/domain', 'name', 'directory.contract.local');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//user', 'id', '2001');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//variable[@name="user_context"]', 'value', 'default');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//variable[@name="effective_caller_id_name"]', 'value', 'Reception Desk');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//variable[@name="effective_caller_id_number"]', 'value', '2001');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//variable[@name="outbound_caller_id_name"]', 'value', 'FreeSWITCH');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//variable[@name="outbound_caller_id_number"]', 'value', '0000000000');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//param[@name="vm-password"]', 'value', '2001');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//param[@name="password"]', 'value', 'secret-pass');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, 'Foreign Desk');
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, $otherExtension->number);
        FreeSwitchXmlAssertions::assertDoesNotContain($xml, (string) $otherTenant->getKey());
    }

    public function test_inactive_extension_is_not_provisioned(): void
    {
        $tenant = $this->createTenant('directory-inactive');
        $owner = $this->actingAsTenantUser($this->createUser('directory-inactive-owner'));
        $this->createMembership($tenant, $owner);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '2002',
            'status' => ExtensionStatus::Suspended->value,
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $this->assertNull(app(DirectoryXmlBuilder::class)->build($extension));
    }

    public function test_extension_from_another_tenant_is_not_provisioned(): void
    {
        $tenant = $this->createTenant('directory-primary');
        $otherTenant = $this->createTenant('directory-secondary');
        $owner = $this->actingAsTenantUser($this->createUser('directory-primary-owner'));
        $otherOwner = $this->actingAsTenantUser($this->createUser('directory-secondary-owner'));

        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherOwner);

        $otherExtension = $this->createExtensionFixture($otherTenant, $otherOwner, [
            'number' => '2010',
            'label' => 'Foreign Extension',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $this->assertNull(app(DirectoryXmlBuilder::class)->build($otherExtension));
    }

    public function test_disabled_tenant_is_not_provisioned(): void
    {
        $tenant = $this->createTenant('directory-disabled', TenantStatus::Suspended);
        $owner = $this->actingAsTenantUser($this->createUser('directory-disabled-owner'));
        $this->createMembership($tenant, $owner);

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '2011',
            'label' => 'Disabled Desk',
        ]);

        app(TenantContext::class)->setTenant($tenant);

        $this->assertNull(app(DirectoryXmlBuilder::class)->build($extension));
    }
}
