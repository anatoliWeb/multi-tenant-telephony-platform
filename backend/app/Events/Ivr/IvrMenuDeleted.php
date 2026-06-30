<?php

namespace App\Events\Ivr;

use App\Models\IvrMenu;

class IvrMenuDeleted
{
    public function __construct(public readonly IvrMenu $ivrMenu)
    {
    }
}
