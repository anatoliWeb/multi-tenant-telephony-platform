<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactPhone>
 */
class ContactPhoneFactory extends Factory
{
    protected $model = ContactPhone::class;

    public function definition(): array
    {
        $raw = '+380671112233';

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'label' => 'work',
            'raw_number' => $raw,
            'normalized_number' => $raw,
            'extension' => null,
            'is_primary' => true,
            'is_sms_capable' => true,
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->getKey(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }
}
