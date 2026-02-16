<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'bml'),

    'providers' => [
        'bml' => [
            'driver' => \App\Services\Payment\BmlPaymentProvider::class,
        ],
    ],
];
