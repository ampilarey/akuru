<?php

namespace App\Domains\Hifz\Providers;

use App\Domains\Hifz\Actions\ListStudentHifzSummariesAction;
use App\Domains\Hifz\Actions\ReadHalaqaReferenceAction;
use App\Domains\Hifz\Actions\ReadQuranReferenceAction;
use App\Support\Contracts\HalaqaReferenceReader;
use App\Support\Contracts\QuranReferenceReader;
use App\Support\Contracts\StudentHifzSummaryReader;
use Illuminate\Support\ServiceProvider;

class HifzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuranReferenceReader::class, ReadQuranReferenceAction::class);
        $this->app->singleton(HalaqaReferenceReader::class, ReadHalaqaReferenceAction::class);
        $this->app->singleton(StudentHifzSummaryReader::class, ListStudentHifzSummariesAction::class);
    }

    public function boot(): void
    {
        //
    }
}
