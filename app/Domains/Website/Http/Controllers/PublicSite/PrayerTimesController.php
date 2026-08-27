<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\PrayerTimes\Actions\HonorPrayerUnsubscribeKeywordAction;
use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Http\Controllers\Controller;
use App\Support\Services\IslamicCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrayerTimesController extends Controller
{
    public function index(Request $request)
    {
        abort_unless((bool) app(GetSettingAction::class)->execute('prayer.public_page_enabled', true), 404);

        $payload = $this->resolve($request);

        return view('public.prayer-times.index', $payload);
    }

    public function json(Request $request): Response
    {
        abort_unless((bool) app(GetSettingAction::class)->execute('prayer.public_page_enabled', true), 404);

        $payload = $this->resolve($request);
        $times = $payload['times'];

        return response()->json([
            'available' => $times['available'] ?? false,
            'island' => $payload['selected'],
            'date' => $payload['date'],
            'hijri' => $payload['hijri'],
            'times' => $times['times'] ?? [],
            'error' => $times['error'] ?? null,
        ]);
    }

    public function smsOptOut(Request $request): Response
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'keyword' => ['required', 'string', 'max:64'],
        ]);

        app(HonorPrayerUnsubscribeKeywordAction::class)->execute($data['phone'], $data['keyword']);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(Request $request): array
    {
        $provider = app(PrayerTimeProviderInterface::class);
        $islands = $provider->listIslands(true);
        $defaultId = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', 0);
        if ($defaultId < 1) {
            $defaultId = (int) ($islands->first()?->id ?? 0);
        }

        $islandId = (int) $request->query('island_id', $defaultId);
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            $nearest = $provider->findNearestIsland((float) $lat, (float) $lng);
            if ($nearest->id > 0) {
                $islandId = $nearest->id;
            }
        }

        $date = $request->query('date')
            ? Carbon::parse((string) $request->query('date'), config('app.timezone'))
            : now()->timezone(config('app.timezone'));

        $dto = $islandId > 0
            ? $provider->resolveForIsland($islandId, $date)
            : null;

        $selected = $islands->first(fn ($island) => $island->id === $islandId);

        return [
            'islands' => $islands,
            'selected' => $selected?->toArray(),
            'date' => $date->toDateString(),
            'times' => $dto?->toArray() ?? ['available' => false, 'times' => [], 'error' => 'No island selected'],
            'hijri' => IslamicCalendarService::gregorianToHijri($date),
            'labels' => [
                'fajr' => __('public.Fajr'),
                'sunrise' => __('public.Sunrise'),
                'dhuhr' => __('public.Dhuhr'),
                'asr' => __('public.Asr'),
                'maghrib' => __('public.Maghrib'),
                'isha' => __('public.Isha'),
            ],
        ];
    }
}
