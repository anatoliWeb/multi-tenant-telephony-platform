<?php

namespace App\Enums\CallLogs;

enum CallEventType: string
{
    case CallCreated = 'call_created';
    case CallInitiated = 'call_initiated';
    case CallRinging = 'call_ringing';
    case CallAnswered = 'call_answered';
    case CallHeld = 'call_held';
    case CallResumed = 'call_resumed';
    case CallCompleted = 'call_completed';
    case CallFailed = 'call_failed';
    case CallCancelled = 'call_cancelled';
}
