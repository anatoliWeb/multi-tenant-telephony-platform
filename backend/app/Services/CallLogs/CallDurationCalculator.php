<?php

namespace App\Services\CallLogs;

use App\Models\CallLog;
use Carbon\CarbonInterface;

class CallDurationCalculator
{
    /**
     * @return array{ringing_seconds:int,talk_seconds:int,billable_seconds:int,total_seconds:int}
     */
    public function calculate(CallLog $callLog): array
    {
        $ringing = $this->diffSeconds($callLog->ringing_at, $callLog->answered_at);
        $talk = $this->diffSeconds($callLog->answered_at, $callLog->ended_at);
        $total = $this->diffSeconds($callLog->started_at, $callLog->ended_at);

        return [
            'ringing_seconds' => $ringing,
            'talk_seconds' => $talk,
            'billable_seconds' => $talk,
            'total_seconds' => $total,
        ];
    }

    private function diffSeconds(?CarbonInterface $from, ?CarbonInterface $to): int
    {
        if (! $from || ! $to) {
            return 0;
        }

        return max(0, (int) $to->diffInSeconds($from, false));
    }
}
