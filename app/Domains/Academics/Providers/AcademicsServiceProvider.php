<?php

namespace App\Domains\Academics\Providers;

use App\Domains\Academics\Actions\RecordClassAttendanceAction;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use Illuminate\Support\ServiceProvider;

class AcademicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendanceWriterInterface::class, RecordClassAttendanceAction::class);
    }

    public function boot(): void
    {
        //
    }
}
