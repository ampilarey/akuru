<?php

namespace App\Domains\Academics\Providers;

use App\Domains\Academics\Actions\RecordClassAttendanceAction;
use App\Domains\Academics\Console\VerifyLegacyAssessmentMigrationCommand;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\Listeners\CloseClassPlacementWhenStudentLeaves;
use App\Domains\People\Events\StudentStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AcademicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendanceWriterInterface::class, RecordClassAttendanceAction::class);
    }

    public function boot(): void
    {
        // The class roster is this domain's, so the write that ends a
        // placement lives here rather than in the People action that changes
        // the pupil's standing (rule 3).
        Event::listen(StudentStatusChanged::class, CloseClassPlacementWhenStudentLeaves::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyLegacyAssessmentMigrationCommand::class,
            ]);
        }
    }
}
