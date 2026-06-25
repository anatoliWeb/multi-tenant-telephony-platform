<?php

namespace App\Services\Contacts;

use App\Enums\Contacts\ContactStatus;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Exceptions\Telephony\TelephonyValidationException;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use App\Models\ContactTag;
use App\Models\User;
use App\Services\Monitoring\StructuredLogContextService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly StructuredLogContextService $structuredLogs,
    ) {
    }

    public function create(array $payload, User $actor): Contact
    {
        return DB::transaction(function () use ($payload, $actor): Contact {
            $tenantId = $this->requireTenantId();
            $this->assertNoDuplicateSignals($tenantId, $payload);
            $contact = Contact::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'display_name' => $this->resolveDisplayName($payload),
                'company_name' => $payload['company_name'] ?? null,
                'job_title' => $payload['job_title'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? ContactStatus::Active->value,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $this->syncPhones($contact, (array) ($payload['phones'] ?? []));
            $this->syncEmails($contact, (array) ($payload['emails'] ?? []));
            $this->syncTags($contact, (array) ($payload['tag_ids'] ?? []));

            $contact->load(['phones', 'emails', 'tags']);

            $this->log('contact.created', $contact);

            return $contact;
        });
    }

    public function update(Contact $contact, array $payload, User $actor): Contact
    {
        return DB::transaction(function () use ($contact, $payload, $actor): Contact {
            $this->assertNoDuplicateSignals((string) $contact->tenant_id, $payload, $contact);
            $contact->update([
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'display_name' => $this->resolveDisplayName($payload, $contact),
                'company_name' => $payload['company_name'] ?? null,
                'job_title' => $payload['job_title'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? ($contact->status?->value ?? ContactStatus::Active->value),
                'updated_by' => $actor->getKey(),
            ]);

            if (array_key_exists('phones', $payload)) {
                $this->syncPhones($contact, (array) $payload['phones']);
            }

            if (array_key_exists('emails', $payload)) {
                $this->syncEmails($contact, (array) $payload['emails']);
            }

            if (array_key_exists('tag_ids', $payload)) {
                $this->syncTags($contact, (array) $payload['tag_ids']);
            }

            $contact->load(['phones', 'emails', 'tags']);

            $this->log('contact.updated', $contact);

            return $contact;
        });
    }

    public function delete(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $contact->delete();
            $this->log('contact.deleted', $contact);
        });
    }

    public function createTag(array $payload): ContactTag
    {
        $tenantId = $this->requireTenantId();
        $slug = Str::slug((string) $payload['name']);

        return ContactTag::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => trim((string) $payload['name']),
            'slug' => $slug,
        ]);
    }

    public function updateTag(ContactTag $tag, array $payload): ContactTag
    {
        $tag->update([
            'name' => trim((string) $payload['name']),
            'slug' => Str::slug((string) $payload['name']),
        ]);

        return $tag->fresh();
    }

    public function deleteTag(ContactTag $tag): void
    {
        $tag->delete();
    }

    private function syncPhones(Contact $contact, array $phones): void
    {
        ContactPhone::query()->where('contact_id', $contact->id)->delete();

        $records = array_values(array_filter($phones, fn (array $phone): bool => filled($phone['raw_number'] ?? null)));
        if ($records === []) {
            return;
        }

        $hasPrimary = collect($records)->contains(fn (array $phone): bool => (bool) ($phone['is_primary'] ?? false));

        foreach ($records as $index => $record) {
            $normalized = $this->phoneNumberNormalizer->normalize(
                (string) $record['raw_number'],
                $record['extension'] ?? null,
            );

            ContactPhone::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->id,
                'label' => $record['label'] ?? 'work',
                'raw_number' => trim((string) $record['raw_number']),
                'normalized_number' => $normalized['normalized_number'],
                'extension' => $normalized['extension'],
                'is_primary' => $hasPrimary ? (bool) ($record['is_primary'] ?? false) : $index === 0,
                'is_sms_capable' => (bool) ($record['is_sms_capable'] ?? false),
                'is_active' => array_key_exists('is_active', $record) ? (bool) $record['is_active'] : true,
            ]);
        }
    }

    private function syncEmails(Contact $contact, array $emails): void
    {
        ContactEmail::query()->where('contact_id', $contact->id)->delete();

        $records = array_values(array_filter($emails, fn (array $email): bool => filled($email['email'] ?? null)));
        if ($records === []) {
            return;
        }

        $hasPrimary = collect($records)->contains(fn (array $email): bool => (bool) ($email['is_primary'] ?? false));

        foreach ($records as $index => $record) {
            $normalizedEmail = mb_strtolower(trim((string) $record['email']));

            ContactEmail::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->id,
                'label' => $record['label'] ?? 'work',
                'email' => trim((string) $record['email']),
                'normalized_email' => $normalizedEmail,
                'is_primary' => $hasPrimary ? (bool) ($record['is_primary'] ?? false) : $index === 0,
                'is_active' => array_key_exists('is_active', $record) ? (bool) $record['is_active'] : true,
            ]);
        }
    }

    private function syncTags(Contact $contact, array $tagIds): void
    {
        if ($tagIds === []) {
            $contact->tags()->sync([]);

            return;
        }

        $resolved = ContactTag::query()
            ->forTenant($contact->tenant_id)
            ->whereIn('id', array_map('intval', $tagIds))
            ->pluck('id')
            ->all();

        if (count($resolved) !== count(array_unique(array_map('intval', $tagIds)))) {
            throw new TelephonyConflictException('Contact tags must belong to the active tenant.');
        }

        $contact->tags()->sync($resolved);
    }

    private function resolveDisplayName(array $payload, ?Contact $contact = null): string
    {
        $displayName = trim((string) ($payload['display_name'] ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }

        $firstName = trim((string) ($payload['first_name'] ?? $contact?->first_name ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? $contact?->last_name ?? ''));
        $companyName = trim((string) ($payload['company_name'] ?? $contact?->company_name ?? ''));

        $candidate = trim($firstName.' '.$lastName);
        if ($candidate !== '') {
            return $candidate;
        }

        if ($companyName !== '') {
            return $companyName;
        }

        throw new TelephonyValidationException('Contact display name is required.');
    }

    private function assertNoDuplicateSignals(string $tenantId, array $payload, ?Contact $contact = null): void
    {
        $phones = array_values(array_filter((array) ($payload['phones'] ?? []), fn (array $phone): bool => filled($phone['raw_number'] ?? null)));
        foreach ($phones as $phone) {
            $normalized = $this->phoneNumberNormalizer->normalize((string) $phone['raw_number'], $phone['extension'] ?? null)['normalized_number'];
            $query = ContactPhone::query()
                ->where('tenant_id', $tenantId)
                ->where('normalized_number', $normalized);

            if ($contact instanceof Contact) {
                $query->where('contact_id', '!=', $contact->id);
            }

            if ($query->exists()) {
                throw new TelephonyConflictException('A contact with this phone number already exists in the active tenant.');
            }
        }

        $emails = array_values(array_filter((array) ($payload['emails'] ?? []), fn (array $email): bool => filled($email['email'] ?? null)));
        foreach ($emails as $email) {
            $normalizedEmail = mb_strtolower(trim((string) $email['email']));
            $query = ContactEmail::query()
                ->where('tenant_id', $tenantId)
                ->where('normalized_email', $normalizedEmail);

            if ($contact instanceof Contact) {
                $query->where('contact_id', '!=', $contact->id);
            }

            if ($query->exists()) {
                throw new TelephonyConflictException('A contact with this email already exists in the active tenant.');
            }
        }

        if ($phones === [] && $emails === []) {
            $displayName = $this->resolveDisplayName($payload, $contact);
            $companyName = trim((string) ($payload['company_name'] ?? $contact?->company_name ?? ''));

            $query = Contact::query()
                ->where('tenant_id', $tenantId)
                ->where('display_name', $displayName)
                ->where('company_name', $companyName === '' ? null : $companyName);

            if ($contact instanceof Contact) {
                $query->where('id', '!=', $contact->id);
            }

            if ($query->exists()) {
                throw new TelephonyConflictException('A matching contact already exists in the active tenant.');
            }
        }
    }

    private function requireTenantId(): string
    {
        return (string) $this->tenantContext->requireTenant()->getKey();
    }

    private function log(string $event, Contact $contact): void
    {
        Log::info($event, $this->structuredLogs->sanitize([
            'module' => 'contacts',
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->id,
            'contact_status' => $contact->status?->value ?? $contact->status,
        ]));
    }
}
