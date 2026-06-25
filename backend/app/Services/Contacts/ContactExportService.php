<?php

namespace App\Services\Contacts;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportService
{
    public function __construct(
        private readonly ContactQueryService $queryService,
    ) {
    }

    public function export(array $filters): StreamedResponse
    {
        $contacts = $this->queryService->search(array_merge($filters, [
            'page' => 1,
            'per_page' => (int) config('contacts.export.max_rows', 5000),
        ]));

        $response = new StreamedResponse(function () use ($contacts): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'display_name',
                'first_name',
                'last_name',
                'company_name',
                'job_title',
                'status',
                'phones',
                'emails',
                'tags',
            ]);

            foreach ($contacts->items() as $contact) {
                fputcsv($handle, [
                    $this->escapeCsv((string) $contact->display_name),
                    $this->escapeCsv((string) $contact->first_name),
                    $this->escapeCsv((string) $contact->last_name),
                    $this->escapeCsv((string) $contact->company_name),
                    $this->escapeCsv((string) $contact->job_title),
                    $this->escapeCsv((string) ($contact->status?->value ?? $contact->status)),
                    $this->escapeCsv($contact->phones->pluck('raw_number')->implode('; ')),
                    $this->escapeCsv($contact->emails->pluck('email')->implode('; ')),
                    $this->escapeCsv($contact->tags->pluck('name')->implode('; ')),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contacts.csv"');

        return $response;
    }

    private function escapeCsv(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
