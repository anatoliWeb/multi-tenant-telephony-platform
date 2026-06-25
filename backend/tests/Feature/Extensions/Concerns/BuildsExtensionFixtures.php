<?php

namespace Tests\Feature\Extensions\Concerns;

use App\Enums\Extensions\ExtensionProvisioningStatus;
use App\Enums\Extensions\ExtensionRegistrationStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Models\Contact;
use App\Models\Extension;
use App\Models\ExtensionCredential;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Contacts\Concerns\BuildsContactFixtures;

trait BuildsExtensionFixtures
{
    use BuildsContactFixtures;

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createExtensionFixture(Tenant $tenant, User $owner, array $overrides = []): Extension
    {
        $contact = $overrides['assigned_contact'] ?? $this->createContactFixture($tenant, $owner, [
            'display_name' => 'Extension Contact',
        ]);

        unset($overrides['assigned_contact']);

        $extension = Extension::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'number' => '2001',
            'label' => 'Support Desk',
            'status' => ExtensionStatus::Active->value,
            'provisioning_status' => ExtensionProvisioningStatus::Provisioned->value,
            'registration_status' => ExtensionRegistrationStatus::Unregistered->value,
            'assigned_user_id' => $owner->getKey(),
            'assigned_contact_id' => $contact instanceof Contact ? $contact->getKey() : null,
            'endpoint_key' => 'extension:'.Str::uuid()->toString(),
            'provider_name' => 'fake',
            'provider_resource_id' => 'endpoint-0001',
            'credential_username' => '2001',
            'last_provisioned_at' => now(),
            'created_by' => $owner->getKey(),
            'updated_by' => $owner->getKey(),
            'metadata' => [
                'provider_state' => [
                    'provider' => 'fake',
                    'endpoint_status' => 'active',
                    'registration_status' => 'unregistered',
                    'address' => 'sip:2001@tenant.invalid',
                    'updated_at' => now()->toISOString(),
                ],
            ],
        ], $overrides));

        ExtensionCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'extension_id' => $extension->getKey(),
            'username' => (string) $extension->number,
            'secret_encrypted' => encrypt('secret-pass'),
            'secret_hint' => 'pass',
            'version' => 1,
            'rotated_by' => $owner->getKey(),
            'rotated_at' => now(),
        ]);

        return $extension->fresh(['credential', 'assignedUser', 'assignedContact']);
    }
}
