<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ListPrayerIslandsAction;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IslandController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManage();

        return view('admin.prayer-times.islands', [
            'islands' => app(ListPrayerIslandsAction::class)->execute(false),
            'cacheVersion' => app(GetSettingAction::class)->execute('prayer_times_cache_version', 1),
            'defaultIslandId' => app(GetSettingAction::class)->execute('prayer.default_island_id'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $this->authorizeManage();
        $rows = app(ListPrayerIslandsAction::class)->execute(false);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name_latin', 'name', 'atoll_latin', 'offset_minutes', 'latitude', 'longitude', 'is_active']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->nameEn,
                    $row->nameDv,
                    $row->atollLatin,
                    $row->offsetMinutes,
                    $row->latitude,
                    $row->longitude,
                    $row->isActive ? 'yes' : 'no',
                ]);
            }
            fclose($out);
        }, 'prayer-islands.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
    }
}
