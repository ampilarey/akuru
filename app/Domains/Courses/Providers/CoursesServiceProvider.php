<?php

namespace App\Domains\Courses\Providers;

use App\Domains\Courses\Components\Quran\Actions\ReadQuranReferenceAction;
use App\Domains\Courses\Components\Quran\Actions\ReadQuranTextAction;
use App\Domains\Courses\Components\Quran\Console\ImportQuranTranslationsCommand;
use App\Domains\Courses\Gradebook\ClassroomAssessmentGradeItemProvider;
use App\Domains\Courses\Listeners\ActivateEnrollmentOnPaymentConfirmed;
use App\Domains\Courses\Listeners\CancelEnrollmentOnPaymentRefunded;
use App\Domains\ExamsGrades\Contracts\GradeItemProvider;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Events\PaymentRefunded;
use App\Support\Contracts\QuranReferenceReader;
use App\Support\Contracts\QuranTextProviderInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CoursesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ClassroomAssessmentGradeItemProvider::class], GradeItemProvider::class);

        // F5 (ADR-025): the Qur'an dataset and its readers moved out of Hifz
        // into Components\Quran, so the engine binds them. The contracts
        // themselves did not change, so no consumer of either one moved.
        $this->app->singleton(QuranReferenceReader::class, ReadQuranReferenceAction::class);
        $this->app->singleton(QuranTextProviderInterface::class, ReadQuranTextAction::class);
    }

    public function boot(): void
    {
        // Phase 4 (rule 12): engine paid enrollments activate on webhook
        // confirmation only; a full refund takes the enrollment back.
        Event::listen(PaymentConfirmed::class, ActivateEnrollmentOnPaymentConfirmed::class);
        Event::listen(PaymentRefunded::class, CancelEnrollmentOnPaymentRefunded::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ImportQuranTranslationsCommand::class]);
        }
    }
}
