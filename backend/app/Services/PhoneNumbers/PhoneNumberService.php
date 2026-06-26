<?php

namespace App\Services\PhoneNumbers;

use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Contacts\PhoneNumberNormalizer;
use App\Services\Monitoring\StructuredLogContextService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PhoneNumberService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PhoneNumberNormalizer $normalizer,
        private readonly PhoneNumberAssignmentService $assignmentService,
        private readonly StructuredLogContextService $structuredLogs,
    ) {
    }

    public function create(array $payload, User $actor): PhoneNumber
    {
        return DB::transaction(function () use ($payload, $actor): PhoneNumber {
            $tenantId = $this->requireTenantId();
            $normalized = $this->normalizer->normalize((string) $payload['number']);
            $this->assertUniqueNumber($tenantId, $normalized['normalized_number']);

            $phoneNumber = PhoneNumber::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'number' => (string) $payload['number'],
                'normalized_number' => $normalized['normalized_number'],
                'display_number' => $payload['display_number'] ?? (string) $payload['number'],
                'type' => $payload['type'] ?? PhoneNumberType::Did->value,
                'status' => $payload['status'] ?? PhoneNumberStatus::Active->value,
                'assignment_status' => PhoneNumberAssignmentStatus::Unassigned->value,
                'assigned_user_id' => null,
                'is_primary' => false,
                'provider_name' => $payload['provider_name'] ?? null,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'country_code' => $payload['country_code'] ?? null,
                'capabilities' => $payload['capabilities'] ?? [],
                'metadata' => $payload['metadata'] ?? [],
                'purchased_at' => $payload['purchased_at'] ?? null,
                'activated_at' => ($payload['status'] ?? PhoneNumberStatus::Active->value) === PhoneNumberStatus::Active->value ? now() : null,
                'released_at' => null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            if (! empty($payload['assigned_user_id'])) {
                $phoneNumber = $this->assignmentService->assign(
                    $phoneNumber,
                    User::query()->findOrFail((int) $payload['assigned_user_id']),
                    (bool) ($payload['is_primary'] ?? false)
                );
            }

            $this->log('phone_number.created', $phoneNumber);

            return $phoneNumber->fresh(['assignedUser.assignedExtensions']);
        });
    }

    public function update(PhoneNumber $phoneNumber, array $payload, User $actor): PhoneNumber
    {
        return DB::transaction(function () use ($phoneNumber, $payload, $actor): PhoneNumber {
            $target = PhoneNumber::query()->whereKey($phoneNumber->getKey())->lockForUpdate()->firstOrFail();
            $number = (string) ($payload['number'] ?? $target->number);
            $normalized = $this->normalizer->normalize($number);
            $this->assertUniqueNumber((string) $target->tenant_id, $normalized['normalized_number'], $target);

            $target->forceFill([
                'number' => $number,
                'normalized_number' => $normalized['normalized_number'],
                'display_number' => $payload['display_number'] ?? $number,
                'type' => $payload['type'] ?? ($target->type?->value ?? $target->type),
                'status' => $payload['status'] ?? ($target->status?->value ?? $target->status),
                'provider_name' => $payload['provider_name'] ?? $target->provider_name,
                'provider_reference' => $payload['provider_reference'] ?? $target->provider_reference,
                'country_code' => $payload['country_code'] ?? $target->country_code,
                'capabilities' => $payload['capabilities'] ?? $target->capabilities,
                'metadata' => $payload['metadata'] ?? $target->metadata,
                'purchased_at' => $payload['purchased_at'] ?? $target->purchased_at,
                'updated_by' => $actor->getKey(),
            ])->save();

            if (array_key_exists('assigned_user_id', $payload)) {
                if ($payload['assigned_user_id']) {
                    $target = $this->assignmentService->assign(
                        $target,
                        User::query()->findOrFail((int) $payload['assigned_user_id']),
                        (bool) ($payload['is_primary'] ?? false)
                    );
                } else {
                    $target = $this->assignmentService->unassign($target);
                }
            } elseif (! empty($payload['is_primary'])) {
                $target = $this->assignmentService->setPrimary($target);
            }

            $this->log('phone_number.updated', $target);

            return $target->fresh(['assignedUser.assignedExtensions']);
        });
    }

    public function activate(PhoneNumber $phoneNumber, User $actor): PhoneNumber
    {
        return $this->transitionStatus($phoneNumber, PhoneNumberStatus::Active, $actor, 'phone_number.activated', [
            'activated_at' => now(),
            'released_at' => null,
        ]);
    }

    public function suspend(PhoneNumber $phoneNumber, User $actor): PhoneNumber
    {
        return $this->transitionStatus($phoneNumber, PhoneNumberStatus::Suspended, $actor, 'phone_number.suspended');
    }

    public function release(PhoneNumber $phoneNumber, User $actor): PhoneNumber
    {
        return DB::transaction(function () use ($phoneNumber, $actor): PhoneNumber {
            $target = $this->assignmentService->unassign($phoneNumber);
            $target->forceFill([
                'status' => PhoneNumberStatus::Released->value,
                'released_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->log('phone_number.released', $target);

            return $target->fresh(['assignedUser.assignedExtensions']);
        });
    }

    public function delete(PhoneNumber $phoneNumber): void
    {
        DB::transaction(function () use ($phoneNumber): void {
            $target = PhoneNumber::query()->whereKey($phoneNumber->getKey())->lockForUpdate()->firstOrFail();
            if (($target->status?->value ?? $target->status) !== PhoneNumberStatus::Released->value) {
                throw new TelephonyConflictException('Only released phone numbers may be deleted.');
            }

            $this->log('phone_number.deleted', $target);
            $target->delete();
        });
    }

    private function transitionStatus(
        PhoneNumber $phoneNumber,
        PhoneNumberStatus $status,
        User $actor,
        string $event,
        array $extra = []
    ): PhoneNumber {
        return DB::transaction(function () use ($phoneNumber, $status, $actor, $event, $extra): PhoneNumber {
            $target = PhoneNumber::query()->whereKey($phoneNumber->getKey())->lockForUpdate()->firstOrFail();
            $target->forceFill(array_merge([
                'status' => $status->value,
                'updated_by' => $actor->getKey(),
            ], $extra))->save();

            $this->log($event, $target);

            return $target->fresh(['assignedUser.assignedExtensions']);
        });
    }

    private function requireTenantId(): string
    {
        return (string) $this->tenantContext->requireTenant()->getKey();
    }

    private function assertUniqueNumber(string $tenantId, string $normalizedNumber, ?PhoneNumber $phoneNumber = null): void
    {
        $query = PhoneNumber::query()
            ->where('tenant_id', $tenantId)
            ->where('normalized_number', $normalizedNumber);

        if ($phoneNumber instanceof PhoneNumber) {
            $query->where('id', '!=', $phoneNumber->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('A phone number with this normalized value already exists in the active tenant.');
        }
    }

    private function log(string $event, PhoneNumber $phoneNumber): void
    {
        Log::info($event, $this->structuredLogs->sanitize([
            'module' => 'phone_numbers',
            'tenant_id' => $phoneNumber->tenant_id,
            'phone_number_id' => $phoneNumber->getKey(),
            'normalized_number' => $phoneNumber->normalized_number,
            'status' => $phoneNumber->status?->value ?? $phoneNumber->status,
            'assignment_status' => $phoneNumber->assignment_status?->value ?? $phoneNumber->assignment_status,
            'is_primary' => (bool) $phoneNumber->is_primary,
        ]));
    }
}
