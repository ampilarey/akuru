<?php

namespace App\Domains\ExamsGrades\Providers;

use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use App\Domains\ExamsGrades\Listeners\RecomputeTermGradesOnPublish;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ExamsGradesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(ExamResultsPublished::class, RecomputeTermGradesOnPublish::class);
    }
}
