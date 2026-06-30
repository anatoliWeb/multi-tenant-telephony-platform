<?php

namespace App\Events\CallQueues;

use App\Models\CallQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallQueueDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly CallQueue $callQueue)
    {
    }
}
