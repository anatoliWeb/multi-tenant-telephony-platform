<?php

namespace Database\Factories;

use App\Models\ContactTag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactTag>
 */
class ContactTagFactory extends Factory
{
    protected $model = ContactTag::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
