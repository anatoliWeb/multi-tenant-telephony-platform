<?php

namespace App\Enums\Ivr;

enum IvrDestinationType: string
{
    case Extension = 'extension';
    case RingGroup = 'ring_group';
    case CallQueue = 'call_queue';
    case IvrMenu = 'ivr_menu';
    case Hangup = 'hangup';
    case VoicemailPlaceholder = 'voicemail_placeholder';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $destination): string => $destination->value, self::cases());
    }
}
