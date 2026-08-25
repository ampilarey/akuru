<?php

namespace App\Domains\Hifz\Providers;

use App\Domains\Hifz\Actions\ReadQuranReferenceAction;
use App\Support\Contracts\QuranReferenceReader;
use Illuminate\Support\ServiceProvider;

class HifzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuranReferenceReader::class, ReadQuranReferenceAction::class);
    }

    public function boot(): void
    {
        //
    }
}
