<?php

namespace Tests\Feature\FreeSwitch;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\Support\FreeSwitchXmlAssertions;
use Tests\TestCase;

class FreeSwitchDirectoryEndpointTest extends TestCase
{
    use BuildsExtensionFixtures;
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'local');
        config()->set('freeswitch.enabled', true);
        config()->set('freeswitch.directory_domain', 'directory.contract.local');
        config()->set('freeswitch.local_demo_credentials', true);
        config()->set('freeswitch.default_sip_password', 'change_me_local_demo_only');
    }

    public function test_directory_endpoint_returns_parseable_xml_for_active_extension(): void
    {
        $tenant = $this->createTenant('directory-endpoint-tenant');
        $otherTenant = $this->createTenant('directory-endpoint-tenant-other');
        $owner = $this->actingAsTenantUser($this->createUser('directory-endpoint-owner'));
        $otherOwner = $this->actingAsTenantUser($this->createUser('directory-endpoint-other-owner'));
        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherOwner);
        config()->set('freeswitch.directory_tenant_id', (string) $tenant->getKey());

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '1001',
            'label' => 'Directory Desk',
        ]);
        $foreignExtension = $this->createExtensionFixture($otherTenant, $otherOwner, [
            'number' => '1999',
            'label' => 'Foreign Desk',
        ]);

        $response = $this->get("/api/v1/freeswitch/directory?user={$extension->number}&domain=directory.contract.local");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $parsed = FreeSwitchXmlAssertions::parse((string) $response->getContent());
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '/document/section[@name="directory"]/domain', 'name', 'directory.contract.local');
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//user', 'id', (string) $extension->number);
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//param[@name="password"]', 'value', 'secret-pass');
        FreeSwitchXmlAssertions::assertDoesNotContain((string) $response->getContent(), $foreignExtension->label);
        FreeSwitchXmlAssertions::assertDoesNotContain((string) $response->getContent(), $foreignExtension->number);
        FreeSwitchXmlAssertions::assertDoesNotContain((string) $response->getContent(), (string) $otherTenant->getKey());
    }

    public function test_directory_endpoint_omits_password_outside_local_demo_mode(): void
    {
        config()->set('freeswitch.local_demo_credentials', false);

        $tenant = $this->createTenant('directory-endpoint-no-password');
        $owner = $this->actingAsTenantUser($this->createUser('directory-endpoint-no-password-owner'));
        $this->createMembership($tenant, $owner);
        config()->set('freeswitch.directory_tenant_id', (string) $tenant->getKey());

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '1002',
            'label' => 'No Password Desk',
        ]);

        $response = $this->get("/api/v1/freeswitch/directory?user={$extension->number}&domain=directory.contract.local");

        $response->assertOk();
        $parsed = FreeSwitchXmlAssertions::parse((string) $response->getContent());
        FreeSwitchXmlAssertions::assertXPathAttribute($parsed, '//user', 'id', (string) $extension->number);
        FreeSwitchXmlAssertions::assertDoesNotContain((string) $response->getContent(), 'name="password"');
        FreeSwitchXmlAssertions::assertDoesNotContain((string) $response->getContent(), 'change_me_local_demo_only');
    }

    public function test_directory_endpoint_returns_404_when_disabled(): void
    {
        config()->set('freeswitch.enabled', false);

        $this->get('/api/v1/freeswitch/directory?user=1001&domain=directory.contract.local')
            ->assertNotFound();
    }

    public function test_directory_endpoint_rejects_unknown_and_cross_tenant_extensions(): void
    {
        $tenant = $this->createTenant('directory-endpoint-primary');
        $otherTenant = $this->createTenant('directory-endpoint-secondary');
        $owner = $this->actingAsTenantUser($this->createUser('directory-endpoint-primary-owner'));
        $otherOwner = $this->actingAsTenantUser($this->createUser('directory-endpoint-secondary-owner'));

        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherOwner);
        config()->set('freeswitch.directory_tenant_id', (string) $tenant->getKey());

        $foreignExtension = $this->createExtensionFixture($otherTenant, $otherOwner, [
            'number' => '1010',
            'label' => 'Foreign Desk',
        ]);

        $this->get('/api/v1/freeswitch/directory?user=9999&domain=directory.contract.local')
            ->assertNotFound();

        $this->get("/api/v1/freeswitch/directory?user={$foreignExtension->number}&domain=directory.contract.local")
            ->assertNotFound();
    }

    public function test_directory_endpoint_rejects_disabled_tenant(): void
    {
        $tenant = $this->createTenant('directory-endpoint-disabled', TenantStatus::Suspended);
        $owner = $this->actingAsTenantUser($this->createUser('directory-endpoint-disabled-owner'));
        $this->createMembership($tenant, $owner);
        config()->set('freeswitch.directory_tenant_id', (string) $tenant->getKey());

        $extension = $this->createExtensionFixture($tenant, $owner, [
            'number' => '1020',
            'status' => ExtensionStatus::Suspended->value,
            'label' => 'Suspended Desk',
        ]);

        $this->get("/api/v1/freeswitch/directory?user={$extension->number}&domain=directory.contract.local")
            ->assertNotFound();
    }
}
