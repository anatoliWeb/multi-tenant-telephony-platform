<?php

namespace App\Services\CallLogs;

use App\Enums\CallLogs\CallDisposition;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CallStatisticsService
{
    public function __construct(
        private readonly CallQueryService $callQueryService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int|float|array<int, array<string, int|string>>>
     */
    public function summarize(User $user, array $filters = []): array
    {
        [$from, $to] = $this->resolveRange($filters);
        $query = $this->callQueryService->visibleQuery($user)
            ->whereBetween('started_at', [$from, $to]);

        $totalCalls = (clone $query)->count();
        $answeredCalls = (clone $query)->where('disposition', CallDisposition::Answered->value)->count();
        $missedCalls = (clone $query)->whereIn('disposition', [
            CallDisposition::NoAnswer->value,
            CallDisposition::Busy->value,
            CallDisposition::Rejected->value,
            CallDisposition::Cancelled->value,
        ])->count();
        $failedCalls = (clone $query)->where('status', TelephonyCallStatus::Failed->value)->count();
        $inboundCalls = (clone $query)->where('direction', TelephonyCallDirection::Inbound->value)->count();
        $outboundCalls = (clone $query)->where('direction', TelephonyCallDirection::Outbound->value)->count();
        $internalCalls = (clone $query)->where('direction', TelephonyCallDirection::Internal->value)->count();
        $totalTalkSeconds = (int) ((clone $query)->sum('talk_seconds') ?? 0);

        return [
            'window' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => $missedCalls,
            'failed_calls' => $failedCalls,
            'inbound_calls' => $inboundCalls,
            'outbound_calls' => $outboundCalls,
            'internal_calls' => $internalCalls,
            'total_talk_seconds' => $totalTalkSeconds,
            'average_talk_seconds' => $totalCalls > 0 ? (float) round($totalTalkSeconds / max(1, $answeredCalls), 2) : 0.0,
            'answer_rate' => $totalCalls > 0 ? (float) round(($answeredCalls / $totalCalls) * 100, 2) : 0.0,
            'calls_by_day' => $this->callsByDay($query),
            'calls_by_status' => $this->callsByStatus($query),
            'calls_by_direction' => $this->callsByDirection($query),
            'top_users' => $this->topUsers($query),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(array $filters): array
    {
        $defaultTo = CarbonImmutable::now()->endOfDay();
        $defaultFrom = $defaultTo->subDays(29)->startOfDay();

        $from = isset($filters['date_from']) && is_string($filters['date_from']) && trim($filters['date_from']) !== ''
            ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
            : $defaultFrom;
        $to = isset($filters['date_to']) && is_string($filters['date_to']) && trim($filters['date_to']) !== ''
            ? CarbonImmutable::parse($filters['date_to'])->endOfDay()
            : $defaultTo;

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        if ($from->diffInDays($to) > 92) {
            $from = $to->subDays(92)->startOfDay();
        }

        return [$from, $to];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function callsByDay(Builder $query): array
    {
        return (clone $query)
            ->selectRaw('DATE(started_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => (string) $row->day,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function callsByStatus(Builder $query): array
    {
        return (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function callsByDirection(Builder $query): array
    {
        return (clone $query)
            ->select('direction', DB::raw('COUNT(*) as total'))
            ->groupBy('direction')
            ->orderBy('direction')
            ->get()
            ->map(fn ($row): array => [
                'direction' => $row->direction instanceof \BackedEnum ? $row->direction->value : (string) $row->direction,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function topUsers(Builder $query): array
    {
        return (clone $query)
            ->leftJoin('users', 'call_logs.caller_user_id', '=', 'users.id')
            ->selectRaw('users.id as user_id, users.name as user_name, COUNT(call_logs.id) as total')
            ->whereNotNull('call_logs.caller_user_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'user_id' => $row->user_id ? (int) $row->user_id : null,
                'user_name' => $row->user_name ? (string) $row->user_name : null,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
