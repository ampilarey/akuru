<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Models\PrayerCategory;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Support\Facades\DB;

class SeedSyntheticPrayerTimesAction
{
    /**
     * Local/testing Maldives fixture (366-day leap table). Not Bake&Grill data.
     *
     * @return array{male_id: int, nearby_id: int, far_id: int}
     */
    public function execute(): array
    {
        return DB::transaction(function () {
            PrayerCategory::query()->updateOrCreate(['id' => 1], ['id' => 1]);
            PrayerCategory::query()->updateOrCreate(['id' => 2], ['id' => 2]);

            PrayerIsland::query()->updateOrCreate(['id' => 1], [
                'category_id' => 1,
                'atoll' => 'މާލެ',
                'atoll_latin' => 'Male',
                'name' => 'މާލެ',
                'name_latin' => 'Malé',
                'offset_minutes' => 0,
                'latitude' => 4.1755,
                'longitude' => 73.5093,
                'is_active' => true,
            ]);
            PrayerIsland::query()->updateOrCreate(['id' => 2], [
                'category_id' => 1,
                'atoll' => 'ކ. އަތޮޅު',
                'atoll_latin' => 'Kaafu',
                'name' => 'ހުޅުމާލެ',
                'name_latin' => 'Hulhumalé',
                'offset_minutes' => 2,
                'latitude' => 4.2100,
                'longitude' => 73.5400,
                'is_active' => true,
            ]);
            PrayerIsland::query()->updateOrCreate(['id' => 3], [
                'category_id' => 2,
                'atoll' => 'ސ. އަތޮޅު',
                'atoll_latin' => 'Seenu',
                'name' => 'ހިތަދޫ',
                'name_latin' => 'Hithadhoo',
                'offset_minutes' => 0,
                'latitude' => -0.6092,
                'longitude' => 73.0890,
                'is_active' => true,
            ]);

            foreach ([1, 2] as $categoryId) {
                for ($day = 1; $day <= 366; $day++) {
                    $fajr = $this->fajrMinutes($day);
                    PrayerTime::query()->updateOrCreate(
                        ['category_id' => $categoryId, 'day_of_year' => $day],
                        [
                            'fajr' => $fajr,
                            'sunrise' => $fajr + 30,
                            'dhuhr' => 735,
                            'asr' => 930,
                            'maghrib' => 1095,
                            'isha' => 1170,
                        ],
                    );
                }
            }

            app(SetSettingAction::class)->execute('prayer.default_island_id', '1', 'string', 'prayer', 'Default prayer island');
            app(SetSettingAction::class)->execute('prayer_times_cache_version', '1', 'string', 'prayer', 'Prayer times cache version');
            app(SetSettingAction::class)->execute('prayer.public_page_enabled', true, 'boolean', 'prayer', 'Public prayer page');
            app(SetSettingAction::class)->execute('prayer.sms_tariff_mvr', '0.40', 'string', 'prayer', 'SMS tariff MVR');
            app(BumpPrayerTimesCacheVersionAction::class)->execute();

            return [
                'male_id' => 1,
                'nearby_id' => 2,
                'far_id' => 3,
            ];
        });
    }

    private function fajrMinutes(int $day): int
    {
        if ($day === 6) {
            return 305;
        }

        return 300 + $day;
    }
}
