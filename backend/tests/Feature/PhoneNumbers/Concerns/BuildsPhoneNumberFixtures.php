<?php

namespace Tests\Feature\PhoneNumbers\Concerns;

use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;

trait BuildsPhoneNumberFixtures
{
    use BuildsExtensionFixtures;

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createPhoneNumberFixture(Tenant $tenant, User $owner, array $overrides = []): PhoneNumber
    {
        $number = (string) ($overrides['number'] ?? '+15550001001');
        $normalized = (string) ($overrides['normalized_number'] ?? $number);
        $assignedUserId = array_key_exists('assigned_user_id', $overrides) ? $overrides['assigned_user_id'] : $owner->getKey();
        $isAssigned = $assignedUserId !== null;
        $isPrimary = $isAssigned ? (bool) ($overrides['is_primary'] ?? false) : false;

        return PhoneNumber::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'number' => $number,
            'normalized_number' => $normalized,
            'display_number' => $overrides['display_number'] ?? $number,
            'type' => PhoneNumberType::Did->value,
            'status' => PhoneNumberStatus::Active->value,
            'assignment_status' => $isAssigned
                ? PhoneNumberAssignmentStatus::Assigned->value
                : PhoneNumberAssignmentStatus::Unassigned->value,
            'assigned_user_id' => $assignedUserId,
            'is_primary' => $isPrimary,
            'primary_assignment_key' => $isPrimary ? $tenant->getKey().'#'.$assignedUserId : null,
            'provider_name' => 'manual',
            'provider_reference' => $overrides['provider_reference'] ?? 'fixture-'.substr($normalized, -4),
            'country_code' => '1',
            'capabilities' => ['voice'],
            'metadata' => $overrides['metadata'] ?? [],
            'purchased_at' => now()->subDays(2),
            'activated_at' => now()->subDay(),
            'released_at' => null,
            'created_by' => $owner->getKey(),
            'updated_by' => $owner->getKey(),
        ], $overrides));
    }
}
