<?php

namespace App\Enums\CallQueues;

enum CallQueueMemberType: string
{
    case Extension = 'extension';
    case User = 'user';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
