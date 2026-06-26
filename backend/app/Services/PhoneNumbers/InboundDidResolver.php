<?php

namespace App\Services\PhoneNumbers;

use App\DTO\Telephony\InboundDidResolutionResult;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Services\Contacts\PhoneNumberNormalizer;

class InboundDidResolver
{
    public function __construct(
        private readonly PhoneNumberNormalizer $normalizer,
    ) {
    }

    public function resolve(string $number, Tenant|string|null $tenant = null): ?InboundDidResolutionResult
    {
        $normalized = $this->normalizer->normalize($number)['normalized_number'];
        $query = PhoneNumber::query()
            ->with(['tenant', 'assignedUser'])
            ->where('normalized_number', $normalized);

        if ($tenant instanceof Tenant) {
            $query->where('tenant_id', $tenant->getKey());
        } elseif (is_string($tenant) && $tenant !== '') {
            $query->where('tenant_id', $tenant);
        }

        $matches = $query->get();

        if ($matches->count() !== 1) {
            return null;
        }

        /** @var PhoneNumber $phoneNumber */
        $phoneNumber = $matches->first();
        $status = $phoneNumber->status?->value ?? (string) $phoneNumber->status;
        $routingAllowed = $status === PhoneNumberStatus::Active->value && $phoneNumber->assigned_user_id !== null;

        return new InboundDidResolutionResult(
            phoneNumber: $phoneNumber,
            tenant: $phoneNumber->tenant,
            assignedUser: $phoneNumber->assignedUser,
            status: $status,
            routingAllowed: $routingAllowed,
        );
    }
}
