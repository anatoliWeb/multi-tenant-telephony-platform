<?php

namespace App\Enums\RingGroups;

enum RingGroupStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
