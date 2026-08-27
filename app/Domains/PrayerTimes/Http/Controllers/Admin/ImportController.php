<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Actions\SeedSyntheticPrayerTimesAction;
use App\Domains\Settings\Actions\GetSettingAction;
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

        $data = $request->validate([
            'salat_db' => ['required', 'file'],
        ]);

        $path = $data['salat_db']->getRealPath();
        try {
            $counts = app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['salat_db' => $e->getMessage()]);
        }

        return back()->with('success', "Imported {$counts['categories']} categories, {$counts['islands']} islands, {$counts['times']} times.");
    }
}
