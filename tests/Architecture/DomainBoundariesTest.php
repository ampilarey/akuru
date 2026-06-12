<?php

/**
 * PHASE_0_CHECKLIST §0.5 rule 3 — no cross-domain concrete service references.
 */
arch('rule 3 cross-domain code uses notification SMS contract not concrete gateway')
    ->expect([
        'App\Domains\Finance',
        'App\Domains\Identity',
        'App\Domains\Admissions',
        'App\Domains\Portal',
    ])
    ->not->toUse('App\Domains\Notifications\Services\SmsGatewayService');

arch('rule 3 cross-domain code uses media image processor contract not concrete service')
    ->expect('App\Domains\Website')
    ->not->toUse('App\Domains\Media\Services\WebPImageService');

arch('rule 3 finance domain owns BML connect service')
    ->expect('App\Domains')
    ->not->toUse('App\Domains\Finance\Services\BmlConnectService')
    ->ignoring([
        'App\Domains\Finance',
        // Baseline grandfather: shrink when CheckoutController uses Finance contract.
        'App\Domains\Admissions\Http\Controllers\CheckoutController',
    ]);
