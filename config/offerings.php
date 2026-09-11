<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Live classes (ROADMAP §2d)
    |--------------------------------------------------------------------------
    |
    | Level 1 — a teacher pastes a meeting link onto a session — needs none of
    | this and is the default. Level 2 (the provider creates the meeting,
    | reports attendance and returns a recording) switches on only when a driver
    | and its credentials are both present.
    |
    | Leaving `driver` as `null` is a supported production configuration, not an
    | unfinished one.
    |
    */
    'video' => [
        'driver' => env('OFFERINGS_VIDEO_DRIVER', 'null'),

        'timeout_seconds' => (int) env('OFFERINGS_VIDEO_TIMEOUT', 10),

        'bigbluebutton' => [
            'base_url' => env('BBB_BASE_URL', ''),
            'secret' => env('BBB_SECRET', ''),
        ],
    ],
];
