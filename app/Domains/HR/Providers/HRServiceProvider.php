<?php

namespace App\Domains\HR\Providers;

use App\Domains\HR\Actions\RecordStaffAttendanceAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use Illuminate\Support\ServiceProvider;

class HRServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StaffAttendanceWriterInterface::class, RecordStaffAttendanceAction::class);
    }

    public function boot(): void
    {
        //
    }
}
