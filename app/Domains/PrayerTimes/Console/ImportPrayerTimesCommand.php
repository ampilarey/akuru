<?php

namespace App\Domains\PrayerTimes\Console;

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use Illuminate\Console\Command;

class ImportPrayerTimesCommand extends Command
{
    protected $signature = 'prayer:import {path : Path to salat.db}';

    protected $description = 'Import Bake&Grill salat.db into prayer_categories / prayer_islands / prayer_times';

    public function handle(ImportPrayerTimesFromSalatDbAction $action): int
    {
        $path = (string) $this->argument('path');
        try {
            $counts = $action->execute($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$counts['categories']} categories, {$counts['islands']} islands, {$counts['times']} prayer time rows.");

        return self::SUCCESS;
    }
}
