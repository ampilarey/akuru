<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Actions\ResolveDefaultPrayerIslandAction;
use App\Domains\PrayerTimes\Actions\SeedSyntheticPrayerTimesAction;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import prayer times: the bundled dataset, an uploaded salat.db or the
 * synthetic fixture. Inertia since C9 slice 12 (STATUS §5jn).
 */
class ImportController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return Inertia::render('PrayerTimes/Import', [
            'cache_version' => (int) app(GetSettingAction::class)->execute('prayer_times_cache_version', 1),
            't' => Phrases::once('admin'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        if ($request->boolean('seed_fixture')) {
            app(SeedSyntheticPrayerTimesAction::class)->execute();

            return back()->with('success', trans('admin.prayer_flash_fixture'));
        }

        if ($request->boolean('use_bundled')) {
            $path = database_path('salat.db');
        } else {
            // The bundled salat.db is under half a megabyte; 20 MB leaves room
            // for a fuller release without letting a stray upload fill the disk
            // (admin-panel audit, STATUS §5hs).
            $data = $request->validate([
                'salat_db' => ['required', 'file', 'max:20480'],
            ]);
            $path = $data['salat_db']->getRealPath();
        }

        try {
            $counts = app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['salat_db' => $e->getMessage()]);
        }

        $this->ensureDefaultIsland();

        return back()->with('success', trans('admin.prayer_flash_imported', $counts));
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
        $resolved = app(ResolveDefaultPrayerIslandAction::class)->execute();

        // Already pointing at a usable island: nothing to write.
        if ($resolved !== null && $resolved === $current) {
            return;
        }

        if ($resolved !== null) {
            app(SetSettingAction::class)->execute('prayer.default_island_id', (string) $resolved, 'string', 'prayer', 'Default prayer island');
            app(SetSettingAction::class)->execute('prayer.public_page_enabled', true, 'boolean', 'prayer', 'Public prayer page');
        }
    }
}
