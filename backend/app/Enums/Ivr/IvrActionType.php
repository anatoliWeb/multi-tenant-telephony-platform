<?php

namespace App\Enums\Ivr;

enum IvrActionType: string
{
    case Repeat = 'repeat';
    case Route = 'route';
    case Hangup = 'hangup';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
