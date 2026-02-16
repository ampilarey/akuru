<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Option 1: Dhiraagu direct (from akurusms config) - uses DHIRAAGU_SMS_USERNAME/PASSWORD
    | Option 2: HTTP gateway - uses SMS_GATEWAY_URL and SMS_GATEWAY_API_KEY
    |
    */
    'sms_gateway' => [
        'url' => env('SMS_GATEWAY_URL', 'https://akuru.edu.mv/api/v2'),
        'api_key' => env('SMS_GATEWAY_API_KEY', ''),
        'enabled' => env('SMS_GATEWAY_ENABLED', true),
        'upstream_url' => env('SMS_UPSTREAM_URL', ''),
        'upstream_api_key' => env('SMS_UPSTREAM_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dhiraagu SMS Configuration (from akurusms)
    |--------------------------------------------------------------------------
    |
    | Direct integration with Dhiraagu Messaging API.
    | Used when SMS_USE_DHIRAAGU=true
    |
    */
    'dhiraagu' => [
        'api_url' => env('DHIRAAGU_API_URL', 'https://messaging.dhiraagu.com.mv/v1/api/sms'),
        'username' => env('DHIRAAGU_SMS_USERNAME', env('DHIRAAGU_USERNAME')),
        'password' => env('DHIRAAGU_SMS_PASSWORD', env('DHIRAAGU_PASSWORD')),
        'enabled' => env('SMS_USE_DHIRAAGU', true),
    ],

    'google' => [
        'analytics_id' => env('GA_MEASUREMENT_ID'),
        'maps_embed_url' => env('GOOGLE_MAPS_EMBED_URL', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.9898!2d73.5088!3d4.1755!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNMKwMTAnMzEuOCJOIDczwrAzMCczMS43IkU!5e0!3m2!1sen!2smv!4v1635000000000!5m2!1sen!2smv'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    | Set ASSET_URL in .env to your CDN URL (e.g. https://cdn.akuru.edu.mv)
    | Laravel will use this for asset() and mix() URLs when configured.
    */
    'cdn' => [
        'url' => env('ASSET_URL'),
        'enabled' => (bool) env('CDN_ENABLED', false),
    ],

];
