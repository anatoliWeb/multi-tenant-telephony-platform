<?php

return [
    'enabled' => (bool) env('FREESWITCH_ENABLED', false),
    'host' => env('FREESWITCH_HOST', 'freeswitch'),
    'ports' => [
        'sip' => (int) env('FREESWITCH_SIP_PORT', 5060),
        'sip_tls' => (int) env('FREESWITCH_SIP_TLS_PORT', 5061),
        'wss' => (int) env('FREESWITCH_WSS_PORT', 7443),
        'rtp_start' => (int) env('FREESWITCH_RTP_START_PORT', 16384),
        'rtp_end' => (int) env('FREESWITCH_RTP_END_PORT', 32768),
        'event_socket' => (int) env('FREESWITCH_EVENT_SOCKET_PORT', 8021),
    ],
    'paths' => [
        'config' => env('FREESWITCH_CONFIG_PATH', '/etc/freeswitch'),
        'recordings' => env('FREESWITCH_RECORDINGS_PATH', '/var/lib/freeswitch/recordings'),
        'logs' => env('FREESWITCH_LOGS_PATH', '/var/log/freeswitch'),
        'tls' => env('FREESWITCH_TLS_PATH', '/etc/freeswitch/tls'),
    ],
    'event_socket_password' => env('FREESWITCH_EVENT_SOCKET_PASSWORD'),
];
