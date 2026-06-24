<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999);

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'status' => TenantStatus::Active->value,
            'timezone' => 'Europe/Kiev',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => now(),
            'suspended_at' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Suspended->value,
            'suspended_at' => now(),
        ]);
    }
}
