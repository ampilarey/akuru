<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcile pending BML payments every 10 minutes (webhook is primary; this is fallback)
Schedule::command('payments:reconcile', ['--older-than' => 5, '--not-updated-in' => 2])->everyTenMinutes();
