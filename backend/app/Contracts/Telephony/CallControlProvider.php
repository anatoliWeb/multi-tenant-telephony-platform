<?php

namespace App\Contracts\Telephony;

use App\DTO\Telephony\TelephonyCallOptions;
use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyCallResult;
use App\DTO\Telephony\TelephonyCallState;
use App\DTO\Telephony\TelephonyTransferRequest;

interface CallControlProvider
{
    public function originateCall(
        TelephonyCallParty $from,
        TelephonyCallParty $to,
        TelephonyCallOptions $options
    ): TelephonyCallResult;

    public function answerCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState;

    public function hangupCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState;

    public function holdCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState;

    public function resumeCall(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState;

    public function transferCall(TelephonyTransferRequest $request): TelephonyCallState;

    public function muteCall(string $tenantId, string $callId, bool $muted, ?string $correlationId = null): TelephonyCallState;

    public function fetchActiveCallState(string $tenantId, string $callId, ?string $correlationId = null): TelephonyCallState;
}
