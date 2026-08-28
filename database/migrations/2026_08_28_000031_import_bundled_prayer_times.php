<?php

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Data backfill: a deployed server with EMPTY prayer tables goes live
 * from the bundled database/salat.db on the next migrate — the TEST
 * deployment answered "no island selected" because nothing ever seeded
 * it. Guards: never touches a table that already has rows (an operator
 * import or the synthetic fixture stays untouched), skips when the
 * bundle is absent, and skips the testing environment so tests keep
 * seeding their own fixtures. Precedent: Bake&Grill backfills prayer
 * data from migrations the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }
        if (! Schema::hasTable('prayer_times') || ! Schema::hasTable('settings')) {
            return;
        }
        $path = database_path('salat.db');
        if (! is_file($path) || PrayerTime::query()->exists()) {
            return;
        }

        app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);

        $male = PrayerIsland::query()->where('name', 'މާލެ')->orderBy('id')->first()
            ?? PrayerIsland::query()->where('is_active', true)->orderBy('id')->first();
        if ($male !== null) {
            $settings = app(SetSettingAction::class);
            $settings->execute('prayer.default_island_id', (string) $male->id, 'string', 'prayer', 'Default prayer island');
            $settings->execute('prayer.public_page_enabled', true, 'boolean', 'prayer', 'Public prayer page');
        }
    }

    public function down(): void
    {
        // Data backfill — no rollback.
    }
};
