<?php

namespace Database\Seeders\Support;

use App\Enums\Contacts\ContactStatus;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use App\Models\ContactTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

class ContactDemoSeedService
{
    /**
     * @return array<string, int>
     */
    public function seed(Tenant $tenant, array $rows): array
    {
        $count = 0;

        foreach ($rows as $row) {
            $owner = User::query()->where('email', $row['owner_email'])->first();
            if (! $owner instanceof User) {
                continue;
            }

            $contact = Contact::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'display_name' => $row['display_name'],
                ],
                [
                    'uuid' => $this->stableUuid($tenant, 'contact-'.$row['display_name']),
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'company_name' => $row['company_name'] ?? null,
                    'job_title' => $row['job_title'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'status' => $row['status'] ?? ContactStatus::Active->value,
                    'created_by' => $owner->getKey(),
                    'updated_by' => $owner->getKey(),
                ]
            );

            $tagIds = collect($row['tags'] ?? [])
                ->map(function (string $tagName) use ($tenant): int {
                    $tag = ContactTag::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->getKey(),
                            'slug' => Str::slug($tagName),
                        ],
                        [
                            'uuid' => $this->stableUuid($tenant, 'tag-'.$tagName),
                            'name' => $tagName,
                        ]
                    );

                    return (int) $tag->getKey();
                })
                ->all();

            $contact->tags()->sync($tagIds);

            ContactPhone::query()->where('contact_id', $contact->id)->delete();
            foreach ($row['phones'] ?? [] as $index => $phone) {
                ContactPhone::query()->create([
                    'uuid' => $this->stableUuid($tenant, 'phone-'.$row['display_name'].'-'.$index),
                    'tenant_id' => $tenant->getKey(),
                    'contact_id' => $contact->id,
                    'label' => $phone['label'] ?? 'work',
                    'raw_number' => $phone['raw_number'],
                    'normalized_number' => $phone['normalized_number'],
                    'extension' => $phone['extension'] ?? null,
                    'is_primary' => (bool) ($phone['is_primary'] ?? false),
                    'is_sms_capable' => (bool) ($phone['is_sms_capable'] ?? false),
                    'is_active' => array_key_exists('is_active', $phone) ? (bool) $phone['is_active'] : true,
                ]);
            }

            ContactEmail::query()->where('contact_id', $contact->id)->delete();
            foreach ($row['emails'] ?? [] as $index => $email) {
                ContactEmail::query()->create([
                    'uuid' => $this->stableUuid($tenant, 'email-'.$row['display_name'].'-'.$index),
                    'tenant_id' => $tenant->getKey(),
                    'contact_id' => $contact->id,
                    'label' => $email['label'] ?? 'work',
                    'email' => $email['email'],
                    'normalized_email' => mb_strtolower($email['email']),
                    'is_primary' => (bool) ($email['is_primary'] ?? false),
                    'is_active' => array_key_exists('is_active', $email) ? (bool) $email['is_active'] : true,
                ]);
            }

            $count++;
        }

        return ['contacts' => $count];
    }

    private function stableUuid(Tenant $tenant, string $key): string
    {
        $hash = substr(sha1((string) $tenant->getKey().':'.$key), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
