<?php

namespace App\Events\Ivr;

use App\Models\IvrMenu;

class IvrMenuUpdated
{
    public function __construct(public readonly IvrMenu $ivrMenu)
    {
    }
}
