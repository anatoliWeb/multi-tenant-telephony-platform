<?php

namespace App\Contracts\Telephony;

use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyConferenceInput;
use App\DTO\Telephony\TelephonyConferenceResult;
use App\DTO\Telephony\TelephonyParticipantResult;

interface ConferenceControlProvider
{
    public function createConference(TelephonyConferenceInput $input): TelephonyConferenceResult;

    public function destroyConference(string $tenantId, string $conferenceId, ?string $correlationId = null): void;

    public function addParticipant(
        string $tenantId,
        string $conferenceId,
        TelephonyCallParty $party,
        ?string $correlationId = null
    ): TelephonyParticipantResult;

    public function removeParticipant(
        string $tenantId,
        string $conferenceId,
        string $participantKey,
        ?string $correlationId = null
    ): void;

    public function muteParticipant(
        string $tenantId,
        string $conferenceId,
        string $participantKey,
        bool $muted,
        ?string $correlationId = null
    ): TelephonyParticipantResult;

    /**
     * @return array<int, TelephonyParticipantResult>
     */
    public function listParticipants(string $tenantId, string $conferenceId, ?string $correlationId = null): array;
}
