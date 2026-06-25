<?php

namespace App\Services\Extensions;

use App\DTO\Telephony\TelephonyEndpointInput;
use App\Enums\Extensions\ExtensionProvisioningStatus;
use App\Enums\Extensions\ExtensionRegistrationStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Enums\Telephony\TelephonyEndpointStatus;
use App\Exceptions\Telephony\TelephonyResourceNotFoundException;
use App\Models\Extension;
use App\Services\Telephony\TelephonyService;
use Illuminate\Support\Arr;

class ExtensionProvisioningService
{
    public function __construct(
        private readonly TelephonyService $telephonyService,
    ) {
    }

    public function provision(Extension $extension, ?string $plainSecret = null): Extension
    {
        $username = (string) $extension->credential_username;
        $metadata = [
            'module' => 'extensions',
            'extension_id' => $extension->getKey(),
            'extension_number' => $extension->number,
            'credential_username' => $username,
            'credential_secret_hint' => $extension->credential?->secret_hint,
            'registration_status' => $extension->status === ExtensionStatus::Suspended
                ? ExtensionRegistrationStatus::Unknown->value
                : ExtensionRegistrationStatus::Unregistered->value,
        ];

        if ($plainSecret !== null) {
            $metadata['credential_secret'] = $plainSecret;
        }

        $input = new TelephonyEndpointInput(
            tenantId: (string) $extension->tenant_id,
            endpointKey: $extension->endpoint_key ?? 'extension:'.$extension->uuid,
            address: sprintf('sip:%s@%s', $username, (string) config('extensions.fake_provider.domain', 'tenant.invalid')),
            displayName: $extension->label ?: 'Extension '.$extension->number,
            desiredStatus: $extension->status === ExtensionStatus::Suspended
                ? TelephonyEndpointStatus::Suspended
                : TelephonyEndpointStatus::Active,
            idempotencyKey: 'extension-provision-'.$extension->uuid.'-v'.((int) ($extension->credential?->version ?? 0)),
            metadata: $metadata,
        );

        if ($extension->endpoint_key) {
            try {
                $result = $this->telephonyService->updateEndpoint($extension->endpoint_key, $input);
            } catch (TelephonyResourceNotFoundException) {
                $result = $this->telephonyService->createEndpoint($input);
            }
        } else {
            $result = $this->telephonyService->createEndpoint($input);
        }

        $providerMetadata = Arr::except($result->metadata, ['credential_secret']);

        $extension->forceFill([
            'endpoint_key' => $result->endpointKey,
            'provider_name' => (string) config('telephony.default_provider', 'fake'),
            'provider_resource_id' => $result->providerResourceId,
            'provisioning_status' => $result->status === TelephonyEndpointStatus::Suspended
                ? ExtensionProvisioningStatus::Suspended
                : ExtensionProvisioningStatus::Provisioned,
            'registration_status' => ExtensionRegistrationStatus::from(
                (string) ($providerMetadata['registration_status'] ?? ExtensionRegistrationStatus::Unknown->value)
            ),
            'last_provisioned_at' => now(),
            'metadata' => array_merge(is_array($extension->metadata) ? $extension->metadata : [], [
                'provider_state' => [
                    'provider' => (string) config('telephony.default_provider', 'fake'),
                    'endpoint_status' => $result->status->value,
                    'registration_status' => (string) ($providerMetadata['registration_status'] ?? ExtensionRegistrationStatus::Unknown->value),
                    'address' => $result->address,
                    'updated_at' => $result->updatedAt,
                ],
            ]),
        ])->save();

        return $extension->fresh(['credential', 'assignedUser', 'assignedContact']);
    }

    public function syncProviderState(Extension $extension): Extension
    {
        if (! filled($extension->endpoint_key)) {
            return $extension;
        }

        try {
            $result = $this->telephonyService->fetchEndpointState((string) $extension->endpoint_key);
        } catch (TelephonyResourceNotFoundException) {
            return $extension;
        }
        $providerMetadata = Arr::except($result->metadata, ['credential_secret']);

        $extension->forceFill([
            'provisioning_status' => $result->status === TelephonyEndpointStatus::Suspended
                ? ExtensionProvisioningStatus::Suspended
                : ExtensionProvisioningStatus::Provisioned,
            'registration_status' => ExtensionRegistrationStatus::from(
                (string) ($providerMetadata['registration_status'] ?? ExtensionRegistrationStatus::Unknown->value)
            ),
            'metadata' => array_merge(is_array($extension->metadata) ? $extension->metadata : [], [
                'provider_state' => [
                    'provider' => (string) config('telephony.default_provider', 'fake'),
                    'endpoint_status' => $result->status->value,
                    'registration_status' => (string) ($providerMetadata['registration_status'] ?? ExtensionRegistrationStatus::Unknown->value),
                    'address' => $result->address,
                    'updated_at' => $result->updatedAt,
                ],
            ]),
        ])->save();

        return $extension->fresh(['credential', 'assignedUser', 'assignedContact']);
    }

    public function delete(Extension $extension): void
    {
        if (filled($extension->endpoint_key)) {
            try {
                $this->telephonyService->deleteEndpoint((string) $extension->endpoint_key);
            } catch (TelephonyResourceNotFoundException) {
                // Treat missing fake-provider state as an idempotent delete.
            }
        }
    }
}
