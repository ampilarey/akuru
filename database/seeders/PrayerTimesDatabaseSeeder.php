<?php

namespace Database\Seeders;

use App\Domains\PrayerTimes\Actions\SeedSyntheticPrayerTimesAction;
use Illuminate\Database\Seeder;

class PrayerTimesDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(SeedSyntheticPrayerTimesAction::class)->execute();
    }
}
