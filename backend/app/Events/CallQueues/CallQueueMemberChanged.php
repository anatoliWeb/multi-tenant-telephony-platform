<?php

namespace App\Events\CallQueues;

use App\Models\CallQueueMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallQueueMemberChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly CallQueueMember $member)
    {
    }
}
