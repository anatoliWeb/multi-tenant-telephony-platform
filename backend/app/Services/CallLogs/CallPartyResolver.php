<?php

namespace App\Services\CallLogs;

use App\DTO\Telephony\InboundDidResolutionResult;
use App\DTO\Telephony\TelephonyCallParty;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Models\Contact;
use App\Models\Extension;
use App\Models\PhoneNumber;
use App\Services\Contacts\ContactQueryService;
use App\Services\Contacts\PhoneNumberNormalizer;
use App\Services\PhoneNumbers\InboundDidResolver;
use App\Services\Tenancy\TenantContext;

class CallPartyResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly ContactQueryService $contactQueryService,
        private readonly InboundDidResolver $inboundDidResolver,
    ) {
    }

    /**
     * @return array{
     *   raw_number: string|null,
     *   normalized_number: string|null,
     *   user_id: int|null,
     *   extension_id: int|null,
     *   phone_number_id: int|null,
     *   contact_id: int|null,
     *   display_name: string|null
     * }
     */
    public function resolve(
        TelephonyCallParty $party,
        TelephonyCallDirection $direction,
        string $side
    ): array {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();
        $rawNumber = $party->number;
        $normalizedNumber = $this->normalize($rawNumber);
        $extension = $this->resolveExtension($tenantId, $party->endpointKey);
        $didResolution = $this->resolveDid($direction, $side, $normalizedNumber, $tenantId);
        $phoneNumber = $didResolution?->phoneNumber
            ?? $this->resolvePhoneNumber($tenantId, $normalizedNumber);
        $contact = $phoneNumber instanceof PhoneNumber
            ? null
            : $this->resolveContact($normalizedNumber);
        $user = $didResolution?->assignedUser ?? $extension?->assignedUser ?? $phoneNumber?->assignedUser;

        return [
            'raw_number' => $rawNumber,
            'normalized_number' => $normalizedNumber,
            'user_id' => $user?->getKey(),
            'extension_id' => $extension?->getKey(),
            'phone_number_id' => $phoneNumber?->getKey(),
            'contact_id' => $contact?->getKey(),
            'display_name' => $party->displayName,
        ];
    }

    private function normalize(?string $number): ?string
    {
        if (! is_string($number) || trim($number) === '') {
            return null;
        }

        try {
            return $this->phoneNumberNormalizer->normalize($number)['normalized_number'];
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveExtension(string $tenantId, ?string $endpointKey): ?Extension
    {
        if (! is_string($endpointKey) || $endpointKey === '') {
            return null;
        }

        return Extension::query()
            ->forTenant($tenantId)
            ->with('assignedUser')
            ->where('endpoint_key', $endpointKey)
            ->first();
    }

    private function resolveDid(
        TelephonyCallDirection $direction,
        string $side,
        ?string $normalizedNumber,
        string $tenantId
    ): ?InboundDidResolutionResult {
        if ($normalizedNumber === null) {
            return null;
        }

        if ($direction === TelephonyCallDirection::Inbound && $side === 'callee') {
            return $this->inboundDidResolver->resolve($normalizedNumber, $tenantId);
        }

        return null;
    }

    private function resolvePhoneNumber(string $tenantId, ?string $normalizedNumber): ?PhoneNumber
    {
        if ($normalizedNumber === null) {
            return null;
        }

        return PhoneNumber::query()
            ->forTenant($tenantId)
            ->with('assignedUser')
            ->where('normalized_number', $normalizedNumber)
            ->first();
    }

    private function resolveContact(?string $normalizedNumber): ?Contact
    {
        if ($normalizedNumber === null) {
            return null;
        }

        return $this->contactQueryService->lookupByPhone($normalizedNumber);
    }
}
