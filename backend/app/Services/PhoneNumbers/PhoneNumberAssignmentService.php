<?php

namespace App\Services\PhoneNumbers;

use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\TenantMembershipStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\PhoneNumber;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PhoneNumberAssignmentService
{
    public function assign(PhoneNumber $phoneNumber, User $user, bool $makePrimary = false): PhoneNumber
    {
        $membership = $this->resolveActiveMembership((string) $phoneNumber->tenant_id, $user->getKey());
        $target = $this->lockPhoneNumber($phoneNumber);

        if ($membership->status !== TenantMembershipStatus::Active) {
            throw new TelephonyConflictException('Assigned user must have an active membership in the current tenant.');
        }

        $currentPrimary = $this->lockCurrentPrimary((string) $target->tenant_id, $user->getKey());
        $shouldBePrimary = $makePrimary || ! $currentPrimary instanceof PhoneNumber;

        if ($target->assigned_user_id !== null && (int) $target->assigned_user_id !== (int) $user->getKey() && (bool) $target->is_primary) {
            $target->forceFill([
                'is_primary' => false,
                'primary_assignment_key' => null,
            ])->save();
        }

        if ($shouldBePrimary && $currentPrimary instanceof PhoneNumber && (int) $currentPrimary->getKey() !== (int) $target->getKey()) {
            $currentPrimary->forceFill([
                'is_primary' => false,
                'primary_assignment_key' => null,
            ])->save();
        }

        $target->forceFill([
            'assigned_user_id' => $user->getKey(),
            'assignment_status' => PhoneNumberAssignmentStatus::Assigned->value,
            'is_primary' => $shouldBePrimary,
            'primary_assignment_key' => $shouldBePrimary
                ? $this->buildPrimaryAssignmentKey((string) $target->tenant_id, $user->getKey())
                : null,
        ])->save();

        return $target->fresh(['assignedUser.assignedExtensions']);
    }

    public function unassign(PhoneNumber $phoneNumber): PhoneNumber
    {
        $target = $this->lockPhoneNumber($phoneNumber);

        $target->forceFill([
            'assigned_user_id' => null,
            'assignment_status' => PhoneNumberAssignmentStatus::Unassigned->value,
            'is_primary' => false,
            'primary_assignment_key' => null,
        ])->save();

        return $target->fresh(['assignedUser.assignedExtensions']);
    }

    public function setPrimary(PhoneNumber $phoneNumber): PhoneNumber
    {
        $target = $this->lockPhoneNumber($phoneNumber);

        if ($target->assigned_user_id === null) {
            throw new TelephonyConflictException('Unassigned phone numbers cannot be primary.');
        }

        $this->resolveActiveMembership((string) $target->tenant_id, (int) $target->assigned_user_id);
        $currentPrimary = $this->lockCurrentPrimary((string) $target->tenant_id, (int) $target->assigned_user_id);

        if ($currentPrimary instanceof PhoneNumber && (int) $currentPrimary->getKey() !== (int) $target->getKey()) {
            $currentPrimary->forceFill([
                'is_primary' => false,
                'primary_assignment_key' => null,
            ])->save();
        }

        $target->forceFill([
            'is_primary' => true,
            'primary_assignment_key' => $this->buildPrimaryAssignmentKey((string) $target->tenant_id, (int) $target->assigned_user_id),
        ])->save();

        return $target->fresh(['assignedUser.assignedExtensions']);
    }

    public function handleRemovedMembership(TenantMembership $membership): void
    {
        if ($membership->status !== TenantMembershipStatus::Removed) {
            return;
        }

        PhoneNumber::query()
            ->where('tenant_id', $membership->tenant_id)
            ->where('assigned_user_id', $membership->user_id)
            ->lockForUpdate()
            ->get()
            ->each(function (PhoneNumber $phoneNumber): void {
                $phoneNumber->forceFill([
                    'assigned_user_id' => null,
                    'assignment_status' => PhoneNumberAssignmentStatus::Unassigned->value,
                    'is_primary' => false,
                    'primary_assignment_key' => null,
                ])->save();
            });
    }

    private function resolveActiveMembership(string $tenantId, int $userId): TenantMembership
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (! $membership instanceof TenantMembership) {
            throw new TelephonyConflictException('Assigned user must belong to the active tenant.');
        }

        if ($membership->status !== TenantMembershipStatus::Active) {
            throw new TelephonyConflictException('Assigned user must have an active membership in the current tenant.');
        }

        return $membership;
    }

    private function lockPhoneNumber(PhoneNumber $phoneNumber): PhoneNumber
    {
        return PhoneNumber::query()
            ->whereKey($phoneNumber->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockCurrentPrimary(string $tenantId, int $userId): ?PhoneNumber
    {
        return PhoneNumber::query()
            ->where('tenant_id', $tenantId)
            ->where('assigned_user_id', $userId)
            ->where('is_primary', true)
            ->lockForUpdate()
            ->first();
    }

    private function buildPrimaryAssignmentKey(string $tenantId, int $userId): string
    {
        return $tenantId.'#'.$userId;
    }
}
