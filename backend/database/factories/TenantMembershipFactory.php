<?php

namespace Database\Factories;

use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantMembership>
 */
class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'status' => TenantMembershipStatus::Active->value,
            'invited_by' => null,
            'invited_at' => null,
            'accepted_at' => now(),
            'activated_at' => now(),
            'suspended_at' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->getKey(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => TenantMembershipStatus::Active->value,
            'accepted_at' => now(),
            'activated_at' => now(),
            'suspended_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => TenantMembershipStatus::Suspended->value,
            'activated_at' => null,
            'suspended_at' => now(),
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn () => [
            'status' => TenantMembershipStatus::Invited->value,
            'invited_at' => now(),
            'accepted_at' => null,
            'activated_at' => null,
            'suspended_at' => null,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn () => [
            'status' => TenantMembershipStatus::Removed->value,
            'accepted_at' => null,
            'activated_at' => null,
            'suspended_at' => null,
        ]);
    }
}
