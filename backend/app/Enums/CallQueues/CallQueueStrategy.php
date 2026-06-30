<?php

namespace App\Enums\CallQueues;

enum CallQueueStrategy: string
{
    case RingAll = 'ring_all';
    case RoundRobin = 'round_robin';
    case LeastRecent = 'least_recent';
    case FewestCalls = 'fewest_calls';
    case Random = 'random';
    case Sequential = 'sequential';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $strategy): string => $strategy->value, self::cases());
    }
}
