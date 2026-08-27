<?php

namespace App\Domains\PrayerTimes\Console;

use App\Domains\PrayerTimes\Actions\BumpPrayerTimesCacheVersionAction;
use Illuminate\Console\Command;

class ClearPrayerTimesCacheCommand extends Command
{
    protected $signature = 'prayer:cache-clear';

    protected $description = 'Bump prayer times cache version (invalidates resolved island/date keys)';

    public function handle(BumpPrayerTimesCacheVersionAction $action): int
    {
        $version = $action->execute();
        $this->info("Prayer times cache version is now {$version}.");

        return self::SUCCESS;
    }
}
