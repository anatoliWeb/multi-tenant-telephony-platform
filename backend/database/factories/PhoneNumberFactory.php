<?php

namespace Database\Factories;

use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhoneNumber>
 */
class PhoneNumberFactory extends Factory
{
    protected $model = PhoneNumber::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'number' => '+1555'.$this->faker->unique()->numerify('#######'),
            'normalized_number' => '+1555'.$this->faker->unique()->numerify('#######'),
            'display_number' => '+1 555 '.$this->faker->numerify('### ####'),
            'type' => PhoneNumberType::Did->value,
            'status' => PhoneNumberStatus::Active->value,
            'assignment_status' => PhoneNumberAssignmentStatus::Unassigned->value,
            'assigned_user_id' => null,
            'is_primary' => false,
            'provider_name' => 'fake',
            'provider_reference' => null,
            'country_code' => '1',
            'capabilities' => ['voice'],
            'metadata' => [],
            'purchased_at' => now(),
            'activated_at' => now(),
            'released_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => PhoneNumberStatus::Active->value]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => PhoneNumberStatus::Suspended->value]);
    }

    public function available(): static
    {
        return $this->state(fn (): array => [
            'status' => PhoneNumberStatus::Available->value,
            'assignment_status' => PhoneNumberAssignmentStatus::Unassigned->value,
            'assigned_user_id' => null,
            'is_primary' => false,
        ]);
    }

    public function assigned(User $user): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $user->tenantMemberships()->first()?->tenant_id,
            'assigned_user_id' => $user->getKey(),
            'assignment_status' => PhoneNumberAssignmentStatus::Assigned->value,
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn (): array => [
            'assigned_user_id' => null,
            'assignment_status' => PhoneNumberAssignmentStatus::Unassigned->value,
            'is_primary' => false,
        ]);
    }

    public function primary(User $user): static
    {
        return $this->assigned($user)->state(fn (): array => [
            'is_primary' => true,
            'primary_assignment_key' => ($user->tenantMemberships()->first()?->tenant_id ?? '').'#'.$user->getKey(),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }

    public function forUser(User $user): static
    {
        return $this->assigned($user);
    }
}
