<?php

namespace Tests\Feature\Contacts\Concerns;

use App\Enums\Contacts\ContactStatus;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;

trait BuildsContactFixtures
{
    use BuildsTenantIsolationFixtures;

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createContactFixture(Tenant $tenant, User $owner, array $overrides = []): Contact
    {
        $contact = Contact::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Fixture',
            'last_name' => 'Contact',
            'display_name' => 'Fixture Contact',
            'company_name' => 'Fixture Corp',
            'job_title' => 'Manager',
            'notes' => 'Fixture notes',
            'status' => ContactStatus::Active->value,
            'created_by' => $owner->getKey(),
            'updated_by' => $owner->getKey(),
        ], $overrides));

        $contact->load(['phones', 'emails', 'tags']);

        return $contact;
    }

    protected function addContactPhone(Contact $contact, string $rawNumber, bool $isPrimary = true): ContactPhone
    {
        return ContactPhone::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->getKey(),
            'label' => 'work',
            'raw_number' => $rawNumber,
            'normalized_number' => $rawNumber,
            'extension' => null,
            'is_primary' => $isPrimary,
            'is_sms_capable' => true,
            'is_active' => true,
        ]);
    }

    protected function addContactEmail(Contact $contact, string $email, bool $isPrimary = true): ContactEmail
    {
        return ContactEmail::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->getKey(),
            'label' => 'work',
            'email' => $email,
            'normalized_email' => mb_strtolower($email),
            'is_primary' => $isPrimary,
            'is_active' => true,
        ]);
    }

    protected function addContactTag(Tenant $tenant, string $name): ContactTag
    {
        return ContactTag::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }
}
