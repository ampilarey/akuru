<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'bml'),

    'providers' => [
        'bml' => [
            'driver' => \App\Domains\Finance\Services\Payment\BmlPaymentProvider::class,
        ],
    ],
];
