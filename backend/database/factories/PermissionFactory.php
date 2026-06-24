<?php

namespace Database\Factories;

use App\Enums\Rbac\PermissionScope;
use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'scope' => PermissionScope::Platform->value,
            'scope_reference' => PermissionScope::Platform->value,
            'description' => ucfirst(str_replace('-', ' ', $name)),
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'scope' => PermissionScope::Platform->value,
            'scope_reference' => PermissionScope::Platform->value,
        ]);
    }

    public function tenant(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'scope' => PermissionScope::Tenant->value,
            'scope_reference' => $tenant->getKey(),
        ]);
    }
}
