<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BML Connect API (Redirect Method)
    |--------------------------------------------------------------------------
    | Sandbox: https://api.uat.merchants.bankofmaldives.com.mv/public
    | Production: set BML_BASE_URL in .env per BML documentation.
    */

    'base_url' => env('BML_BASE_URL', 'https://api.uat.merchants.bankofmaldives.com.mv/public'),
    'app_id' => env('BML_APP_ID', env('BML_CLIENT_ID')),
    'api_key' => env('BML_API_KEY', env('BML_CLIENT_SECRET')),
    'merchant_id' => env('BML_MERCHANT_ID'),

    /*
    | Webhook (PRIMARY method for payment confirmation). Redirect is not authoritative.
    */
    'webhook_secret' => env('BML_WEBHOOK_SECRET', env('BML_CALLBACK_SECRET')),
    'webhook_url' => env('BML_WEBHOOK_URL', env('BML_CALLBACK_URL')),

    /*
    | Redirect URL for user after payment on BML page.
    */
    'return_url' => env('BML_RETURN_URL'),

    /*
    | Optional: force provider (e.g. "card", "bmlpay"). Omit to let user choose on BML page.
    */
    'provider' => env('BML_PROVIDER'),

    'default_currency' => env('BML_DEFAULT_CURRENCY', 'MVR'),
    'environment' => env('BML_ENVIRONMENT', 'sandbox'),

    /*
    | Webhook signature verification. If BML provides a header name, set it here.
    | Leave null to use default X-BML-Signature or implement per Stoplight docs.
    */
    'webhook_signature_header' => env('BML_WEBHOOK_SIGNATURE_HEADER', 'X-BML-Signature'),
    'webhook_hmac_algo' => env('BML_WEBHOOK_HMAC_ALGO', 'sha256'),

    /*
    | Optional IP allowlist for webhook (comma-separated). Empty = no allowlist.
    */
    'webhook_ip_allowlist' => array_filter(array_map('trim', explode(',', env('BML_WEBHOOK_IP_ALLOWLIST', '')))),

    /*
    | API paths (BML Connect v2 – see Redirect Method docs).
    */
    'paths' => [
        'create_transaction' => '/v2/transactions',
        'get_transaction' => '/v2/transactions/{reference}',
    ],

    /*
    | Optional: paymentPortalExperience (v2) – terms URL and pre-accepted T&C.
    */
    'payment_portal_experience' => [
        'external_website_terms_accepted' => env('BML_EXTERNAL_TERMS_ACCEPTED', true),
        'external_website_terms_url' => env('BML_EXTERNAL_TERMS_URL'), // e.g. https://yoursite.mv/terms
        'skip_provider_selection' => env('BML_SKIP_PROVIDER_SELECTION', false),
    ],
];
