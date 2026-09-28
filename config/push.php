<?php

/*
 * Push notifications to the mobile app (SPEC §50, STATUS §5jr).
 *
 * The app shell registers its device token at /account/devices; every in-app
 * notification then also goes to the person's active devices through
 * `PushSenderInterface` (rule 4). Which sender:
 *
 *   PUSH_DRIVER=null  nothing leaves the building and the app records so (default)
 *   PUSH_DRIVER=log   the log sender: written to the log, counted as sent (staging, tests)
 *   PUSH_DRIVER=fcm   Firebase Cloud Messaging, HTTP v1, with a service-account key
 *
 * `fcm` falls back to `null` when the project id or the key file is missing,
 * so a half-configured host cannot pretend to deliver.
 */
return [
    'driver' => env('PUSH_DRIVER', 'null'),

    'fcm' => [
        // The Firebase project id (Project settings → General).
        'project_id' => env('FCM_PROJECT_ID'),
        // A service-account JSON key with the "Firebase Cloud Messaging API"
        // role, stored outside the web root; the path is all the app holds.
        'credentials' => env('FCM_CREDENTIALS_PATH'),
        'timeout' => (int) env('FCM_TIMEOUT', 5),
    ],
];
