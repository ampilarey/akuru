<?php

arch('cross-domain code uses notification SMS contract not concrete gateway')
    ->expect([
        'App\Domains\Finance',
        'App\Domains\Identity',
        'App\Domains\Admissions',
        'App\Domains\Portal',
    ])
    ->not->toUse('App\Domains\Notifications\Services\SmsGatewayService');

arch('cross-domain code uses media image processor contract not concrete service')
    ->expect('App\Domains\Website')
    ->not->toUse('App\Domains\Media\Services\WebPImageService');

arch('finance domain owns BML connect service')
    ->expect('App\Domains')
    ->not->toUse('App\Domains\Finance\Services\BmlConnectService')
    ->ignoring('App\Domains\Finance');
