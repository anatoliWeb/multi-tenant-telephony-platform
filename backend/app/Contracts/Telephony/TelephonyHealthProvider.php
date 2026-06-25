<?php

namespace App\Contracts\Telephony;

use App\DTO\Telephony\TelephonyProviderHealth;

interface TelephonyHealthProvider
{
    public function health(): TelephonyProviderHealth;
}
