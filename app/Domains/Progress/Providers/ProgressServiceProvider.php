<?php

namespace App\Domains\Progress\Providers;

use App\Domains\Progress\Actions\EvaluateCourseCompletionAction;
use App\Domains\Progress\Actions\EvaluateLessonUnlockAction;
use App\Domains\Progress\Contracts\CourseCompletionEvaluator;
use App\Domains\Progress\Contracts\LessonUnlockEvaluator;
use Illuminate\Support\ServiceProvider;

class ProgressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ROADMAP §2a strategy bindings. Swap these to change pedagogy without
        // touching the engine — see ADR-022.
        $this->app->bind(LessonUnlockEvaluator::class, EvaluateLessonUnlockAction::class);
        $this->app->bind(CourseCompletionEvaluator::class, EvaluateCourseCompletionAction::class);
    }

    public function boot(): void
    {
        //
    }
}
