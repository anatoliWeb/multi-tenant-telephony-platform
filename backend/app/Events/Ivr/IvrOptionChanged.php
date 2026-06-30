<?php

namespace App\Events\Ivr;

use App\Models\IvrOption;

class IvrOptionChanged
{
    public function __construct(public readonly IvrOption $ivrOption)
    {
    }
}
