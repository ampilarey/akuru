<?php

namespace App\Domains\Courses\Providers;

use App\Domains\Courses\Gradebook\ClassroomAssessmentGradeItemProvider;
use App\Domains\Courses\Listeners\ActivateEnrollmentOnPaymentConfirmed;
use App\Domains\ExamsGrades\Contracts\GradeItemProvider;
use App\Domains\Finance\Events\PaymentConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CoursesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ClassroomAssessmentGradeItemProvider::class], GradeItemProvider::class);
    }

    public function boot(): void
    {
        // Phase 4 (rule 12): engine paid enrollments activate on webhook
        // confirmation only.
        Event::listen(PaymentConfirmed::class, ActivateEnrollmentOnPaymentConfirmed::class);
    }
}
