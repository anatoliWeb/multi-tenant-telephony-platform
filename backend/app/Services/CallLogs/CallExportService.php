<?php

namespace App\Services\CallLogs;

use App\Models\CallLog;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallExportService
{
    public function __construct(
        private readonly CallQueryService $queryService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function export(User $user, array $filters): StreamedResponse
    {
        $rows = $this->queryService->exportRows(
            $user,
            $filters,
            (int) config('call_logs.export.max_rows', 5000)
        );

        $response = new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Call ID',
                'External Call ID',
                'Direction',
                'Status',
                'Disposition',
                'Source',
                'Destination',
                'Source Number',
                'Destination Number',
                'Extension',
                'Phone Number',
                'Started At',
                'Rang At',
                'Answered At',
                'Ended At',
                'Duration Seconds',
                'Billable Seconds',
                'Hangup Cause',
                'Correlation ID',
                'Created At',
            ]);

            foreach ($rows as $callLog) {
                fputcsv($handle, [
                    $this->escapeCsv((string) $callLog->id),
                    $this->escapeCsv((string) $callLog->provider_call_id),
                    $this->escapeCsv((string) ($callLog->direction?->value ?? $callLog->direction)),
                    $this->escapeCsv((string) ($callLog->status?->value ?? $callLog->status)),
                    $this->escapeCsv((string) ($callLog->disposition?->value ?? $callLog->disposition)),
                    $this->escapeCsv($this->resolvePartyLabel($callLog, 'caller')),
                    $this->escapeCsv($this->resolvePartyLabel($callLog, 'callee')),
                    $this->escapeCsv((string) $callLog->from_number),
                    $this->escapeCsv((string) $callLog->to_number),
                    $this->escapeCsv($this->resolvePrimaryExtension($callLog)),
                    $this->escapeCsv($this->resolvePrimaryPhoneNumber($callLog)),
                    $this->escapeCsv($this->formatTimestamp($callLog->started_at)),
                    $this->escapeCsv($this->formatTimestamp($callLog->ringing_at)),
                    $this->escapeCsv($this->formatTimestamp($callLog->answered_at)),
                    $this->escapeCsv($this->formatTimestamp($callLog->ended_at)),
                    $this->escapeCsv((string) (int) $callLog->total_seconds),
                    $this->escapeCsv((string) (int) $callLog->billable_seconds),
                    $this->escapeCsv((string) $callLog->hangup_cause),
                    $this->escapeCsv((string) $callLog->correlation_id),
                    $this->escapeCsv($this->formatTimestamp($callLog->created_at)),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="call-logs.csv"');

        return $response;
    }

    private function resolvePartyLabel(CallLog $callLog, string $side): string
    {
        $relations = [
            $callLog->{$side.'User'}?->name,
            $callLog->{$side.'Contact'}?->display_name,
            $callLog->{$side.'Extension'}?->number,
            $callLog->{$side.'PhoneNumber'}?->display_number,
            $callLog->{$side.'PhoneNumber'}?->number,
            $side === 'caller' ? $callLog->from_number : $callLog->to_number,
        ];

        foreach ($relations as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolvePrimaryExtension(CallLog $callLog): string
    {
        $extension = $callLog->callerExtension?->number
            ?? $callLog->calleeExtension?->number
            ?? '';

        return (string) $extension;
    }

    private function resolvePrimaryPhoneNumber(CallLog $callLog): string
    {
        $phoneNumber = $callLog->callerPhoneNumber?->display_number
            ?? $callLog->calleePhoneNumber?->display_number
            ?? $callLog->callerPhoneNumber?->number
            ?? $callLog->calleePhoneNumber?->number
            ?? '';

        return (string) $phoneNumber;
    }

    private function formatTimestamp(?CarbonInterface $timestamp): string
    {
        if (! $timestamp instanceof CarbonInterface) {
            return '';
        }

        $timezone = $this->tenantContext->tenant()?->timezone ?? config('app.timezone');

        return $timestamp->copy()->setTimezone($timezone)->format('Y-m-d H:i:s');
    }

    private function escapeCsv(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
