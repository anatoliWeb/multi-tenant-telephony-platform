<?php

namespace App\Enums\RingGroups;

enum RingGroupStrategy: string
{
    case Simultaneous = 'simultaneous';
    case Sequential = 'sequential';
    case Random = 'random';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $strategy): string => $strategy->value, self::cases());
    }
}
