<?php

namespace App\Services\Extensions;

use App\Enums\Extensions\ExtensionProvisioningStatus;
use App\Enums\Extensions\ExtensionRegistrationStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\Contact;
use App\Models\Extension;
use App\Models\User;
use App\Services\Monitoring\StructuredLogContextService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExtensionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ExtensionNumberNormalizer $numberNormalizer,
        private readonly ExtensionCredentialService $credentialService,
        private readonly ExtensionProvisioningService $provisioningService,
        private readonly StructuredLogContextService $structuredLogs,
    ) {
    }

    /**
     * @return array{extension: Extension, plain_secret: string}
     */
    public function create(array $payload, User $actor): array
    {
        return DB::transaction(function () use ($payload, $actor): array {
            $tenantId = $this->requireTenantId();
            $number = $this->numberNormalizer->normalize((string) $payload['number']);
            $this->assertUniqueNumber($tenantId, $number);
            [$assignedUserId, $assignedContactId] = $this->resolveAssignments($tenantId, $payload);

            $extension = Extension::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'number' => $number,
                'label' => $payload['label'] ?? null,
                'status' => $payload['status'] ?? ExtensionStatus::Active->value,
                'provisioning_status' => ExtensionProvisioningStatus::Pending->value,
                'registration_status' => ExtensionRegistrationStatus::Unknown->value,
                'assigned_user_id' => $assignedUserId,
                'assigned_contact_id' => $assignedContactId,
                'endpoint_key' => null,
                'provider_name' => (string) config('telephony.default_provider', 'fake'),
                'credential_username' => $number,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
                'metadata' => [],
            ]);

            $rotation = $this->credentialService->rotate($extension, $actor, $number);
            $extension = $this->provisioningService->provision($extension->fresh(['credential']), $rotation['plain_secret']);
            $this->log('extension.created', $extension);

            return [
                'extension' => $extension,
                'plain_secret' => $rotation['plain_secret'],
            ];
        });
    }

    /**
     * @return array{extension: Extension, plain_secret: string}
     */
    public function rotateCredentials(Extension $extension, User $actor): array
    {
        return DB::transaction(function () use ($extension, $actor): array {
            $rotation = $this->credentialService->rotate($extension, $actor, (string) $extension->number);
            $extension->forceFill([
                'credential_username' => (string) $extension->number,
                'updated_by' => $actor->getKey(),
            ])->save();

            $fresh = $this->provisioningService->provision($extension->fresh(['credential', 'assignedUser', 'assignedContact']), $rotation['plain_secret']);
            $this->log('extension.credentials_rotated', $fresh);

            return [
                'extension' => $fresh,
                'plain_secret' => $rotation['plain_secret'],
            ];
        });
    }

    public function update(Extension $extension, array $payload, User $actor): Extension
    {
        return DB::transaction(function () use ($extension, $payload, $actor): Extension {
            $tenantId = (string) $extension->tenant_id;
            $number = array_key_exists('number', $payload)
                ? $this->numberNormalizer->normalize((string) $payload['number'])
                : $extension->number;

            $this->assertUniqueNumber($tenantId, $number, $extension);
            [$assignedUserId, $assignedContactId] = $this->resolveAssignments($tenantId, $payload, $extension);

            $extension->update([
                'number' => $number,
                'label' => $payload['label'] ?? $extension->label,
                'status' => $payload['status'] ?? ($extension->status?->value ?? ExtensionStatus::Active->value),
                'assigned_user_id' => $assignedUserId,
                'assigned_contact_id' => $assignedContactId,
                'credential_username' => $number,
                'updated_by' => $actor->getKey(),
            ]);

            if ($extension->credential instanceof \App\Models\ExtensionCredential) {
                $extension->credential->update(['username' => $number]);
            }

            $fresh = $this->provisioningService->provision($extension->fresh(['credential', 'assignedUser', 'assignedContact']));
            $this->log('extension.updated', $fresh);

            return $fresh;
        });
    }

    public function syncProviderState(Extension $extension): Extension
    {
        return $this->provisioningService->syncProviderState($extension);
    }

    public function delete(Extension $extension): void
    {
        DB::transaction(function () use ($extension): void {
            $this->provisioningService->delete($extension);
            $extension->delete();
            $this->log('extension.deleted', $extension);
        });
    }

    private function requireTenantId(): string
    {
        return (string) $this->tenantContext->requireTenant()->getKey();
    }

    private function assertUniqueNumber(string $tenantId, string $number, ?Extension $extension = null): void
    {
        $query = Extension::query()
            ->where('tenant_id', $tenantId)
            ->where('number', $number);

        if ($extension instanceof Extension) {
            $query->where('id', '!=', $extension->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('An extension with this number already exists in the active tenant.');
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveAssignments(string $tenantId, array $payload, ?Extension $extension = null): array
    {
        $assignedUserId = array_key_exists('assigned_user_id', $payload)
            ? ($payload['assigned_user_id'] ? (int) $payload['assigned_user_id'] : null)
            : $extension?->assigned_user_id;
        $assignedContactId = array_key_exists('assigned_contact_id', $payload)
            ? ($payload['assigned_contact_id'] ? (int) $payload['assigned_contact_id'] : null)
            : $extension?->assigned_contact_id;

        if ($assignedUserId !== null) {
            $exists = User::query()
                ->whereKey($assignedUserId)
                ->whereHas('tenantMemberships', fn ($builder) => $builder
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'))
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Assigned user must belong to the active tenant.');
            }
        }

        if ($assignedContactId !== null) {
            $exists = Contact::query()
                ->forTenant($tenantId)
                ->whereKey($assignedContactId)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('Assigned contact must belong to the active tenant.');
            }
        }

        return [$assignedUserId, $assignedContactId];
    }

    private function log(string $event, Extension $extension): void
    {
        Log::info($event, $this->structuredLogs->sanitize([
            'module' => 'extensions',
            'tenant_id' => $extension->tenant_id,
            'extension_id' => $extension->getKey(),
            'number' => $extension->number,
            'status' => $extension->status?->value ?? $extension->status,
            'provisioning_status' => $extension->provisioning_status?->value ?? $extension->provisioning_status,
        ]));
    }
}
