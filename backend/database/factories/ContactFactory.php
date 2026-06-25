<?php

namespace Database\Factories;

use App\Enums\Contacts\ContactStatus;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName.' '.$lastName,
            'company_name' => fake()->optional()->company(),
            'job_title' => fake()->optional()->jobTitle(),
            'notes' => fake()->optional()->sentence(),
            'status' => ContactStatus::Active->value,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ContactStatus::Active->value]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ContactStatus::Archived->value]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => ContactStatus::Blocked->value]);
    }
}
