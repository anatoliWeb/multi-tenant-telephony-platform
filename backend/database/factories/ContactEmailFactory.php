<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactEmail>
 */
class ContactEmailFactory extends Factory
{
    protected $model = ContactEmail::class;

    public function definition(): array
    {
        $email = fake()->safeEmail();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'label' => 'work',
            'email' => $email,
            'normalized_email' => mb_strtolower($email),
            'is_primary' => true,
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
}
