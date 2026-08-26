<?php

namespace App\Domains\ExamsGrades\Providers;

use App\Domains\ExamsGrades\Contracts\GradeItemProvider;
use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use App\Domains\ExamsGrades\Gradebook\ExamGradeItemProvider;
use App\Domains\ExamsGrades\Gradebook\GradeItemRegistry;
use App\Domains\ExamsGrades\Listeners\RecomputeTermGradesOnPublish;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ExamsGradesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GradeItemRegistry::class, function ($app): GradeItemRegistry {
            $registry = new GradeItemRegistry;
            foreach ($app->tagged(GradeItemProvider::class) as $provider) {
                $registry->register($provider);
            }

            return $registry;
        });

        $this->app->tag([ExamGradeItemProvider::class], GradeItemProvider::class);
    }

    public function boot(): void
    {
        Event::listen(ExamResultsPublished::class, RecomputeTermGradesOnPublish::class);
    }
}
