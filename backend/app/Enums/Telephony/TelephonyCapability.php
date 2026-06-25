<?php

namespace App\Enums\Telephony;

enum TelephonyCapability: string
{
    case EndpointProvisioning = 'endpoint_provisioning';
    case CallOrigination = 'call_origination';
    case CallAnswer = 'call_answer';
    case CallHangup = 'call_hangup';
    case CallHold = 'call_hold';
    case CallResume = 'call_resume';
    case CallTransfer = 'call_transfer';
    case CallMute = 'call_mute';
    case ConferenceControl = 'conference_control';
    case Recording = 'recording';
    case Voicemail = 'voicemail';
    case Presence = 'presence';
    case WebhookEvents = 'webhook_events';
}
