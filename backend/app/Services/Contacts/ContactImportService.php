<?php

namespace App\Services\Contacts;

use App\Models\ContactTag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ContactImportService
{
    public function __construct(
        private readonly ContactService $contactService,
    ) {
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function validate(UploadedFile $file): array
    {
        return $this->parse($file, false, null);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function import(UploadedFile $file, User $actor): array
    {
        return $this->parse($file, true, $actor);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    private function parse(UploadedFile $file, bool $persist, ?User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fgetcsv($handle ?: null);
        $rows = [];
        $created = 0;
        $errors = 0;

        $headers = collect($header ?: [])->map(fn ($value) => trim((string) $value))->all();
        $tagMap = ContactTag::query()->forCurrentTenant()->get()->keyBy('name');

        $line = 1;
        while ($handle !== false && ($data = fgetcsv($handle)) !== false) {
            $line++;
            $row = array_combine($headers, $data ?: []) ?: [];
            $phones = $this->splitMultiValue($row['phones'] ?? '');
            $emails = $this->splitMultiValue($row['emails'] ?? '');
            $tags = $this->splitMultiValue($row['tags'] ?? '');

            $result = [
                'row' => $line,
                'status' => 'valid',
                'errors' => [],
                'payload' => [
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'display_name' => $row['display_name'] ?? null,
                    'company_name' => $row['company_name'] ?? null,
                    'job_title' => $row['job_title'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'phones' => collect($phones)->map(fn (string $phone): array => ['raw_number' => $phone])->values()->all(),
                    'emails' => collect($emails)->map(fn (string $email): array => ['email' => $email])->values()->all(),
                    'tag_ids' => collect($tags)
                        ->map(fn (string $tag) => $tagMap->get($tag)?->id)
                        ->filter()
                        ->values()
                        ->all(),
                ],
            ];

            if (collect($tags)->contains(fn (string $tag): bool => ! $tagMap->has($tag))) {
                $result['status'] = 'invalid';
                $result['errors'][] = 'Unknown tag names are not allowed during import.';
            }

            try {
                if ($persist && $actor !== null && $result['status'] === 'valid') {
                    $this->contactService->create($result['payload'], $actor);
                    $created++;
                }
            } catch (\Throwable $exception) {
                $result['status'] = 'invalid';
                $result['errors'][] = mb_substr($exception->getMessage(), 0, 255);
            }

            if ($result['status'] !== 'valid') {
                $errors++;
            }

            $rows[] = $result;
        }

        if ($handle !== false) {
            fclose($handle);
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'created' => $created,
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitMultiValue(string $value): array
    {
        return Collection::make(explode(';', $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
