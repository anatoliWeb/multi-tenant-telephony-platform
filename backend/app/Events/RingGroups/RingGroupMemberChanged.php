<?php

namespace App\Events\RingGroups;

use App\Models\RingGroupMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RingGroupMemberChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly RingGroupMember $member)
    {
    }
}
