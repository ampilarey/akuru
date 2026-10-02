<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ListPrayerIslandsAction;
use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The prayer islands list — the prayer-times hub. Inertia since C9 slice 12
 * (STATUS §5jn), with its strings keyed for Dhivehi and Arabic.
 */
class IslandController extends Controller
{
    public function index(): Response
    {
        $this->authorizeManage();

        return Inertia::render('PrayerTimes/Islands', [
            'islands' => app(ListPrayerIslandsAction::class)->execute(false)->map(fn (IslandDTO $island) => $island->toArray())->values()->all(),
            'cache_version' => (int) app(GetSettingAction::class)->execute('prayer_times_cache_version', 1),
            'default_island_id' => app(GetSettingAction::class)->execute('prayer.default_island_id'),
            't' => Phrases::once('admin'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $this->authorizeManage();
        $rows = app(ListPrayerIslandsAction::class)->execute(false);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'name_latin', 'name', 'atoll_latin', 'offset_minutes', 'latitude', 'longitude', 'is_active']);
            foreach ($rows as $row) {
                Csv::put($out, [
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
