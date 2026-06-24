<?php

namespace Database\Factories;

use App\Enums\Rbac\RoleScope;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'scope' => RoleScope::Platform->value,
            'scope_reference' => RoleScope::Platform->value,
            'tenant_id' => null,
            'description' => ucfirst(str_replace('-', ' ', $name)),
            'is_system' => false,
            'is_protected' => false,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'scope' => RoleScope::Platform->value,
            'scope_reference' => RoleScope::Platform->value,
            'tenant_id' => null,
        ]);
    }

    public function tenant(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'scope' => RoleScope::Tenant->value,
            'scope_reference' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'is_system' => true,
        ]);
    }

    public function protected(): static
    {
        return $this->state(fn () => [
            'is_protected' => true,
        ]);
    }
}
