<?php

namespace App\Domains\PrayerTimes\Providers;

use App\Domains\PrayerTimes\Actions\PrayerTimeProvider;
use App\Domains\PrayerTimes\Console\ClearPrayerTimesCacheCommand;
use App\Domains\PrayerTimes\Console\ImportPrayerTimesCommand;
use App\Domains\PrayerTimes\Console\RunChangeOnlyPrayerReminderCommand;
use App\Domains\PrayerTimes\Console\RunDailyPrayerReminderCommand;
use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
use Illuminate\Support\ServiceProvider;

class PrayerTimesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PrayerTimeProviderInterface::class, PrayerTimeProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportPrayerTimesCommand::class,
                ClearPrayerTimesCacheCommand::class,
                RunDailyPrayerReminderCommand::class,
                RunChangeOnlyPrayerReminderCommand::class,
            ]);
        }
    }
}
