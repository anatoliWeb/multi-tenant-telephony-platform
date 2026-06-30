<?php

namespace App\Events\Ivr;

use App\Models\IvrMenu;

class IvrMenuCreated
{
    public function __construct(public readonly IvrMenu $ivrMenu)
    {
    }
}
