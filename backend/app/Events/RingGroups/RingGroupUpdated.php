<?php

namespace App\Events\RingGroups;

use App\Models\RingGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RingGroupUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly RingGroup $ringGroup)
    {
    }
}
