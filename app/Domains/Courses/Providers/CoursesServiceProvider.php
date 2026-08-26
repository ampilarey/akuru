<?php

namespace App\Domains\Courses\Providers;

use App\Domains\Courses\Gradebook\ClassroomAssessmentGradeItemProvider;
use App\Domains\ExamsGrades\Contracts\GradeItemProvider;
use Illuminate\Support\ServiceProvider;

class CoursesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ClassroomAssessmentGradeItemProvider::class], GradeItemProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
