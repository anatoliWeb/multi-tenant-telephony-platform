<?php

namespace Tests\Feature\Contacts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Contacts\Concerns\BuildsContactFixtures;
use Tests\TestCase;

class ContactLookupImportExportTest extends TestCase
{
    use BuildsContactFixtures;
    use RefreshDatabase;

    public function test_lookup_phone_is_tenant_scoped_even_for_same_number(): void
    {
        $tenantA = $this->createTenant('lookup-a');
        $tenantB = $this->createTenant('lookup-b');
        $user = $this->actingAsTenantUser($this->createUser('lookup-user'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['contacts.view']);
        $this->assignTenantPermissions($user, $tenantB, ['contacts.view']);

        $contactA = $this->createContactFixture($tenantA, $user, ['display_name' => 'Tenant A Contact']);
        $contactB = $this->createContactFixture($tenantB, $user, ['display_name' => 'Tenant B Contact']);
        $this->addContactPhone($contactA, '+15557770000');
        $this->addContactPhone($contactB, '+15557770000');

        $this->getJson('/api/v1/contacts/lookup-phone?phone=%2B15557770000', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Tenant A Contact');

        $this->getJson('/api/v1/contacts/lookup-phone?phone=%2B15557770000', ['X-Tenant-ID' => $tenantB->id])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Tenant B Contact');
    }

    public function test_import_validation_and_export_are_tenant_safe_and_escape_csv_formulas(): void
    {
        $tenantA = $this->createTenant('import-a');
        $tenantB = $this->createTenant('import-b');
        $user = $this->actingAsTenantUser($this->createUser('import-user'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['contacts.view', 'contacts.create', 'contacts.import', 'contacts.export', 'contacts.manage_tags']);
        $this->assignTenantPermissions($user, $tenantB, ['contacts.view', 'contacts.export']);

        $vip = $this->addContactTag($tenantA, 'VIP');
        $this->createContactFixture($tenantB, $user, ['display_name' => '=Tenant B Hidden']);
        $this->addContactPhone($this->createContactFixture($tenantA, $user, ['display_name' => '=Tenant A Visible']), '+15556667777');

        $csv = <<<CSV
display_name,first_name,last_name,company_name,phones,emails,tags,tenant_id
Imported Person,Imported,Person,Import Corp,+15550000001,imported@example.test,VIP,{$tenantB->id}
CSV;

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $this->post('/api/v1/contacts/import/validate', ['file' => $file], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.rows.0.status', 'valid');

        $importFile = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $this->post('/api/v1/contacts/import', ['file' => $importFile], ['X-Tenant-ID' => $tenantA->id])
            ->assertCreated()
            ->assertJsonPath('data.summary.created', 1);

        $response = $this->get('/api/v1/contacts/export', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString("'=Tenant A Visible", $content);
        $this->assertStringContainsString('Imported Person', $content);
        $this->assertStringNotContainsString('Tenant B Hidden', $content);
    }
}
