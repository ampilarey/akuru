<?php

namespace Database\Seeders;

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Actions\SeedSyntheticPrayerTimesAction;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Database\Seeder;

class PrayerTimesDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Real Maldivian dataset (Bake&Grill salat.db) when present —
        // PRAYER_TIMES_DB can point elsewhere, mirroring Bake&Grill's
        // seeder contract. Synthetic 366-day fixture otherwise.
        $path = env('PRAYER_TIMES_DB', database_path('salat.db'));

        if (! is_file($path)) {
            app(SeedSyntheticPrayerTimesAction::class)->execute();

            return;
        }

        app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);

        $male = PrayerIsland::query()->where('name', 'މާލެ')->orderBy('id')->first()
            ?? PrayerIsland::query()->where('is_active', true)->orderBy('id')->first();

        $settings = app(SetSettingAction::class);
        $settings->execute('prayer.default_island_id', (string) ($male?->id ?? 1), 'string', 'prayer', 'Default prayer island');
        $settings->execute('prayer.public_page_enabled', true, 'boolean', 'prayer', 'Public prayer page');
        $settings->execute('prayer.sms_tariff_mvr', '0.40', 'string', 'prayer', 'SMS tariff MVR');
    }
}
