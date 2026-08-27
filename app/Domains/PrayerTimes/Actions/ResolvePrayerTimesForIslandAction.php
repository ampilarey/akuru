<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\PrayerTimesDTO;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use App\Domains\PrayerTimes\Support\LeapYearDayIndex;
use App\Domains\PrayerTimes\Support\MinutesToClock;
use App\Domains\Settings\Actions\GetSettingAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ResolvePrayerTimesForIslandAction
{
    public function execute(int $islandId, Carbon $date): PrayerTimesDTO
    {
        $date = $date->copy()->timezone(config('app.timezone'));
        $dateKey = $date->toDateString();
        $version = (int) app(GetSettingAction::class)->execute('prayer_times_cache_version', 1);
        $cacheKey = "prayer_times:v{$version}:{$islandId}:{$dateKey}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return PrayerTimesDTO::fromCached($cached);
        }

        $resolved = $this->lookup($islandId, $date);
        if ($resolved->available) {
            Cache::forever($cacheKey, $resolved->toArray());
        }

        return $resolved;
    }

    private function lookup(int $islandId, Carbon $date): PrayerTimesDTO
    {
        $island = PrayerIsland::query()->find($islandId);
        if ($island === null) {
            return PrayerTimesDTO::unavailable('Unknown island', $islandId, $date->toDateString());
        }

        $index = LeapYearDayIndex::for($date);
        $row = PrayerTime::query()
            ->where('category_id', $island->category_id)
            ->where('day_of_year', $index)
            ->first();

        if ($row === null) {
            return PrayerTimesDTO::unavailable('Prayer times unavailable', $islandId, $date->toDateString());
        }

        $offset = (int) $island->offset_minutes;
        $fajr = (int) $row->fajr + $offset;
        $sunrise = (int) $row->sunrise + $offset;
        $dhuhr = (int) $row->dhuhr + $offset;
        $asr = (int) $row->asr + $offset;
        $maghrib = (int) $row->maghrib + $offset;
        $isha = (int) $row->isha + $offset;

        return new PrayerTimesDTO(
            available: true,
            islandId: (int) $island->id,
            nameEn: (string) $island->name_latin,
            nameDv: (string) $island->name,
            nameAr: (string) $island->name_latin,
            date: $date->toDateString(),
            fajr: MinutesToClock::format($fajr),
            sunrise: MinutesToClock::format($sunrise),
            dhuhr: MinutesToClock::format($dhuhr),
            asr: MinutesToClock::format($asr),
            maghrib: MinutesToClock::format($maghrib),
            isha: MinutesToClock::format($isha),
            fajrMinutes: $fajr,
            sunriseMinutes: $sunrise,
            dhuhrMinutes: $dhuhr,
            asrMinutes: $asr,
            maghribMinutes: $maghrib,
            ishaMinutes: $isha,
        );
    }
}
