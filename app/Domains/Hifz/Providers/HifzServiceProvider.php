<?php

namespace App\Domains\Hifz\Providers;

use App\Domains\Hifz\Actions\ListStudentHifzSummariesAction;
use App\Domains\Hifz\Actions\ReadHalaqaReferenceAction;
use App\Domains\Hifz\Actions\WriteHifzMilestonesAction;
use App\Support\Contracts\HalaqaMilestoneWriter;
use App\Support\Contracts\HalaqaReferenceReader;
use App\Support\Contracts\StudentHifzSummaryReader;
use Illuminate\Support\ServiceProvider;

class HifzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HalaqaReferenceReader::class, ReadHalaqaReferenceAction::class);
        $this->app->singleton(HalaqaMilestoneWriter::class, WriteHifzMilestonesAction::class);
        $this->app->singleton(StudentHifzSummaryReader::class, ListStudentHifzSummariesAction::class);
    }
}
