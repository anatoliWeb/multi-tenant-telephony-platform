<?php

namespace App\Enums\CallLogs;

enum CallBillingStatus: string
{
    case Unrated = 'unrated';
    case Rated = 'rated';
    case NonBillable = 'non_billable';
    case Failed = 'failed';
}
