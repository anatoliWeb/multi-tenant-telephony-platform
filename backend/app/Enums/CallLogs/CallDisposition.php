<?php

namespace App\Enums\CallLogs;

enum CallDisposition: string
{
    case Answered = 'answered';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
