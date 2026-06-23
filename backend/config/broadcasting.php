<?php

return [

    'default' => env('BROADCAST_DRIVER', env('BROADCAST_CONNECTION', 'reverb')),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                // Separate broadcast host lets Docker backend reach Reverb service
                // while browser clients still use public REVERB_HOST.
                'host' => env('REVERB_BROADCAST_HOST', env('REVERB_HOST', '127.0.0.1')),
                'port' => env('REVERB_PORT', 6001),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
