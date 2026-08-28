<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Actions\SeedSyntheticPrayerTimesAction;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return view('admin.prayer-times.import', [
            'cacheVersion' => app(GetSettingAction::class)->execute('prayer_times_cache_version', 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        if ($request->boolean('seed_fixture')) {
            app(SeedSyntheticPrayerTimesAction::class)->execute();

            return back()->with('success', 'Synthetic 366-day Malé fixture imported (not Bake&Grill).');
        }

        if ($request->boolean('use_bundled')) {
            $path = database_path('salat.db');
        } else {
            $data = $request->validate([
                'salat_db' => ['required', 'file'],
            ]);
            $path = $data['salat_db']->getRealPath();
        }

        try {
            $counts = app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['salat_db' => $e->getMessage()]);
        }

        $this->ensureDefaultIsland();

        return back()->with('success', "Imported {$counts['categories']} categories, {$counts['islands']} islands, {$counts['times']} times.");
    }

    /**
     * A fresh deployment has no default island, which leaves the public
     * page and API answering "no island selected" even after a full
     * import — default to Malé (or the first active island) when the
     * setting is missing or points at an island that no longer exists.
     */
    private function ensureDefaultIsland(): void
    {
        $current = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', '0');
        if ($current > 0 && PrayerIsland::query()->whereKey($current)->exists()) {
            return;
        }

        $island = PrayerIsland::query()->where('name', 'މާލެ')->orderBy('id')->first()
            ?? PrayerIsland::query()->where('is_active', true)->orderBy('id')->first();
        if ($island !== null) {
            app(SetSettingAction::class)->execute('prayer.default_island_id', (string) $island->id, 'string', 'prayer', 'Default prayer island');
            app(SetSettingAction::class)->execute('prayer.public_page_enabled', true, 'boolean', 'prayer', 'Public prayer page');
        }
    }
}
