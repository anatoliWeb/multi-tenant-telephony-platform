<?php

use App\Enums\Telephony\TelephonyCapability;
use App\Enums\Telephony\TelephonyProviderStatus;

return [
    'enabled' => (bool) env('TELEPHONY_ENABLED', false),
    'default_provider' => env('TELEPHONY_DEFAULT_PROVIDER', 'fake'),
    'timeouts' => [
        'request_ms' => (int) env('TELEPHONY_TIMEOUT_REQUEST_MS', 3000),
    ],
    'retry' => [
        'max_attempts' => (int) env('TELEPHONY_RETRY_MAX_ATTEMPTS', 1),
        'backoff_ms' => (int) env('TELEPHONY_RETRY_BACKOFF_MS', 250),
    ],
    'idempotency' => [
        'enabled' => true,
        'ttl_seconds' => (int) env('TELEPHONY_IDEMPOTENCY_TTL_SECONDS', 3600),
    ],
    'logging' => [
        'redact_numbers' => (bool) env('TELEPHONY_LOG_REDACT_NUMBERS', true),
        'mask_character' => '*',
        'show_last_digits' => 4,
    ],
    'providers' => [
        'fake' => [
            'enabled' => true,
            'display_name' => 'Fake Telephony Provider',
            'version' => 'fake-1.0',
            'capabilities' => [
                TelephonyCapability::EndpointProvisioning->value,
                TelephonyCapability::CallOrigination->value,
                TelephonyCapability::CallAnswer->value,
                TelephonyCapability::CallHangup->value,
                TelephonyCapability::CallHold->value,
                TelephonyCapability::CallResume->value,
                TelephonyCapability::CallTransfer->value,
                TelephonyCapability::CallMute->value,
                TelephonyCapability::ConferenceControl->value,
            ],
            'health' => [
                'status' => TelephonyProviderStatus::Healthy->value,
                'latency_ms' => 5,
                'degraded_reasons' => [],
            ],
            'failures' => [],
        ],
    ],
];
