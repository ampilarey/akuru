<?php

use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\PrayerTimes\Actions\ComparePrayerTimesAcrossRangeAction;
use App\Domains\PrayerTimes\Actions\ConfirmPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\FindNearestIslandAction;
use App\Domains\PrayerTimes\Actions\HonorPrayerUnsubscribeKeywordAction;
use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Actions\PreviewPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\RunDailyPrayerReminderAction;
use App\Domains\PrayerTimes\Actions\SavePrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\SendPrayerBroadcastAction;
use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastRecipientStatus;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerBroadcastRecipient;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use App\Domains\Settings\Actions\GetSettingAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function consentedPrayerUser(string $phone = '+9607772434'): array
{
    $admin = actingPeopleAdmin(['prayer.manage']);
    $user = makePrayerContactUser($phone);
    $student = makeStudent(['user_id' => $user->id]);
    app(RecordConsentAction::class)->execute('student', $student->id, 'prayer_reminders', true, $admin->id, 'admin');

    return compact('admin', 'user', 'student');
}

function makeBroadcast(int $adminId, int $islandId, array $overrides = []): PrayerBroadcast
{
    return app(SavePrayerBroadcastAction::class)->execute(array_merge([
        'mode' => 'daily',
        'island_id' => $islandId,
        'date_from' => '2025-01-10',
        'language' => 'en',
        'recipient_refs' => [],
    ], $overrides), null, $adminId);
}

it('fails import when a category does not have 366 rows and succeeds on a valid fixture', function () {
    $short = sys_get_temp_dir().'/salat-short-'.uniqid().'.db';
    sqliteSalatFixture($short, 365);
    expect(fn () => app(ImportPrayerTimesFromSalatDbAction::class)->execute($short))
        ->toThrow(RuntimeException::class, '366');

    $ok = sys_get_temp_dir().'/salat-ok-'.uniqid().'.db';
    sqliteSalatFixture($ok, 366, 2);
    $counts = app(ImportPrayerTimesFromSalatDbAction::class)->execute($ok);
    expect($counts['categories'])->toBe(2)
        ->and($counts['islands'])->toBe(2)
        ->and($counts['times'])->toBe(732)
        ->and(PrayerTime::query()->where('category_id', 1)->count())->toBe(366);

    $again = app(ImportPrayerTimesFromSalatDbAction::class)->execute($ok);
    expect($again['times'])->toBe(732)
        ->and(PrayerTime::query()->where('category_id', 1)->count())->toBe(366);
});

it('indexes non-leap March 1 as dayOfYear+1 and leap years as-is', function () {
    seedPrayerTimesFixture();
    $provider = app(PrayerTimeProviderInterface::class);

    $feb28n = $provider->resolveForIsland(1, Carbon::parse('2025-02-28', 'Indian/Maldives'));
    $mar1n = $provider->resolveForIsland(1, Carbon::parse('2025-03-01', 'Indian/Maldives'));
    $dec31n = $provider->resolveForIsland(1, Carbon::parse('2025-12-31', 'Indian/Maldives'));
    expect($feb28n->fajr)->toBe('05:59')
        ->and($mar1n->fajr)->toBe('06:01')
        ->and($dec31n->fajr)->toBe('11:06');

    $mar1l = $provider->resolveForIsland(1, Carbon::parse('2024-03-01', 'Indian/Maldives'));
    expect($mar1l->fajr)->toBe('06:01');
});

it('applies island offset and formats HH:MM in Maldives timezone', function () {
    seedPrayerTimesFixture();
    $provider = app(PrayerTimeProviderInterface::class);
    $male = $provider->resolveForIsland(1, Carbon::parse('2025-01-10', 'Indian/Maldives'));
    $nearby = $provider->resolveForIsland(2, Carbon::parse('2025-01-10', 'Indian/Maldives'));
    expect($male->fajr)->toBe('05:10')
        ->and($nearby->fajr)->toBe('05:12')
        ->and($nearby->sunrise)->toBe('05:42');
});

it('does not cache null lookups and version bump misses old keys', function () {
    seedPrayerTimesFixture();
    $provider = app(PrayerTimeProviderInterface::class);
    $date = Carbon::parse('2025-01-10', 'Indian/Maldives');
    $version = (int) app(GetSettingAction::class)->execute('prayer_times_cache_version', 1);
    $provider->resolveForIsland(1, $date);
    expect(Cache::has("prayer_times:v{$version}:1:2025-01-10"))->toBeTrue();

    $missing = $provider->resolveForIsland(9999, $date);
    expect($missing->available)->toBeFalse()
        ->and(Cache::has("prayer_times:v{$version}:9999:2025-01-10"))->toBeFalse();

    $this->artisan('prayer:cache-clear')->assertSuccessful();
    $next = (int) app(GetSettingAction::class)->execute('prayer_times_cache_version');
    expect($next)->toBeGreaterThan($version)
        ->and(Cache::has("prayer_times:v{$next}:1:2025-01-10"))->toBeFalse();
});

it('finds the nearest island with Haversine golden coordinates', function () {
    seedPrayerTimesFixture();
    $hulhumale = app(FindNearestIslandAction::class)->execute(4.2100, 73.5400);
    $male = app(FindNearestIslandAction::class)->execute(4.1755, 73.5093);
    expect($hulhumale->id)->toBe(2)
        ->and($hulhumale->nameEn)->toBe('Hulhumalé')
        ->and($male->id)->toBe(1);
});

it('resolves prayer times through the container contract only', function () {
    seedPrayerTimesFixture();
    $provider = app(PrayerTimeProviderInterface::class);
    expect($provider)->toBeInstanceOf(\App\Domains\PrayerTimes\Actions\PrayerTimeProvider::class);
    $dto = $provider->resolveForIsland(1, Carbon::parse('2025-01-10'));
    expect($dto->available)->toBeTrue();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.prayer-times', ['island_id' => 1, 'date' => '2025-01-10']))
        ->assertOk()
        ->assertSee('05:10')
        ->assertSee(__('public.Fajr'));

    $this->get('/api/v1/prayer-times?island_id=1&date=2025-01-10')
        ->assertOk()
        ->assertJsonPath('times.fajr', '05:10');
});

it('range compare returns one block when unchanged and multiple when times change', function () {
    seedPrayerTimesFixture();
    $same = app(ComparePrayerTimesAcrossRangeAction::class)->execute(
        1,
        Carbon::parse('2025-01-05'),
        Carbon::parse('2025-01-06'),
    );
    $changing = app(ComparePrayerTimesAcrossRangeAction::class)->execute(
        1,
        Carbon::parse('2025-01-10'),
        Carbon::parse('2025-01-12'),
    );
    expect($same->isUniform())->toBeTrue()
        ->and($same->blocks)->toHaveCount(1)
        ->and($changing->isUniform())->toBeFalse()
        ->and(count($changing->blocks))->toBeGreaterThan(1);
});

it('change-only sends when tomorrow differs and skips when identical', function () {
    seedPrayerTimesFixture();
    $ctx = consentedPrayerUser();
    $sender = app(SmsSenderInterface::class);
    expect($sender)->toBeInstanceOf(LogSmsSender::class);

    Carbon::setTestNow(Carbon::parse('2025-01-05 12:00:00', 'Indian/Maldives'));
    $skip = makeBroadcast($ctx['admin']->id, 1, [
        'mode' => 'change_only',
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    app(PreviewPrayerBroadcastAction::class)->execute($skip);
    app(ConfirmPrayerBroadcastAction::class)->execute($skip->fresh(), $ctx['admin']->id);
    expect($sender->sent)->toHaveCount(0)
        ->and($skip->fresh()->recipients->first()->status)->toBe(PrayerBroadcastRecipientStatus::Skipped);

    Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00', 'Indian/Maldives'));
    $send = makeBroadcast($ctx['admin']->id, 1, [
        'mode' => 'change_only',
        'date_from' => '2025-01-10',
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    app(PreviewPrayerBroadcastAction::class)->execute($send);
    app(ConfirmPrayerBroadcastAction::class)->execute($send->fresh(), $ctx['admin']->id);
    expect($sender->sent)->toHaveCount(1)
        ->and($send->fresh()->status)->toBe(PrayerBroadcastStatus::Completed);

    Carbon::setTestNow();
});

it('excludes contacts without prayer_reminders consent and honors STOP', function () {
    seedPrayerTimesFixture();
    $admin = actingPeopleAdmin(['prayer.manage']);
    $plain = makePrayerContactUser('+9607771001');
    makeStudent(['user_id' => $plain->id]);
    $ctx = consentedPrayerUser('+9607772434');

    $broadcast = makeBroadcast($admin->id, 1, [
        'recipient_refs' => [
            ['type' => 'user', 'id' => $plain->id],
            ['type' => 'user', 'id' => $ctx['user']->id],
        ],
    ]);
    $previewed = app(PreviewPrayerBroadcastAction::class)->execute($broadcast);
    expect($previewed->preview_snapshot['included_count'])->toBe(1)
        ->and($previewed->preview_snapshot['excluded_count'])->toBe(1);

    app(HonorPrayerUnsubscribeKeywordAction::class)->execute('+9607772434', 'STOP', $ctx['admin']->id);
    $again = makeBroadcast($admin->id, 1, [
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    $stopped = app(PreviewPrayerBroadcastAction::class)->execute($again);
    expect($stopped->preview_snapshot['included_count'])->toBe(0);

    expect(fn () => app(ConfirmPrayerBroadcastAction::class)->execute($stopped, $admin->id))
        ->toThrow(InvalidArgumentException::class, 'No consented recipients');
});

it('sends only through the fake SMS inbox and never the gateway', function () {
    seedPrayerTimesFixture();
    $ctx = consentedPrayerUser();
    $sender = app(SmsSenderInterface::class);
    expect($sender)->toBeInstanceOf(LogSmsSender::class);

    $broadcast = makeBroadcast($ctx['admin']->id, 1, [
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    app(PreviewPrayerBroadcastAction::class)->execute($broadcast);
    app(ConfirmPrayerBroadcastAction::class)->execute($broadcast->fresh(), $ctx['admin']->id);

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]['phone'])->toBe('+9607772434')
        ->and($sender->sent[0]['body'])->toContain('STOP')
        ->and($sender->sent[0]['body'])->toContain('Fajr');
});

it('does not double-send on a duplicate scheduler tick', function () {
    seedPrayerTimesFixture();
    $ctx = consentedPrayerUser();
    $sender = app(SmsSenderInterface::class);

    $broadcast = makeBroadcast($ctx['admin']->id, 1, [
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    app(PreviewPrayerBroadcastAction::class)->execute($broadcast);
    app(ConfirmPrayerBroadcastAction::class)->execute($broadcast->fresh(), $ctx['admin']->id);
    expect($sender->sent)->toHaveCount(1);

    app(SendPrayerBroadcastAction::class)->execute($broadcast->fresh());
    app(RunDailyPrayerReminderAction::class)->execute('2025-01-10');
    app(RunDailyPrayerReminderAction::class)->execute('2025-01-10');
    expect($sender->sent)->toHaveCount(1);
});

it('cannot queue a draft without a preview snapshot', function () {
    seedPrayerTimesFixture();
    $ctx = consentedPrayerUser();
    $broadcast = makeBroadcast($ctx['admin']->id, 1, [
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    expect($broadcast->status)->toBe(PrayerBroadcastStatus::Draft)
        ->and(fn () => app(ConfirmPrayerBroadcastAction::class)->execute($broadcast, $ctx['admin']->id))
        ->toThrow(InvalidArgumentException::class, 'previewed');
});

it('blocks a changing range until it is split', function () {
    seedPrayerTimesFixture();
    $ctx = consentedPrayerUser();
    $broadcast = makeBroadcast($ctx['admin']->id, 1, [
        'mode' => 'range',
        'date_from' => '2025-01-10',
        'date_to' => '2025-01-12',
        'recipient_refs' => [['type' => 'user', 'id' => $ctx['user']->id]],
    ]);
    $previewed = app(PreviewPrayerBroadcastAction::class)->execute($broadcast);
    expect($previewed->preview_snapshot['needs_split'])->toBeTrue();
    expect(fn () => app(ConfirmPrayerBroadcastAction::class)->execute($previewed, $ctx['admin']->id))
        ->toThrow(InvalidArgumentException::class, 'Split');
});

it('serves admin island CSV and public homepage widget from the contract', function () {
    seedPrayerTimesFixture();
    $admin = actingPeopleAdmin(['prayer.manage']);
    $csv = $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get(route('admin.prayer-times.islands.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Malé')->toContain('Hulhumalé');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertSee(__('public.Prayer times'), false);
});

it('new PrayerTimes and Website files stay on contracts and the fake sender', function () {
    $files = collect(\Illuminate\Support\Facades\File::allFiles(app_path('Domains/PrayerTimes')))
        ->map(fn ($file) => $file->getPathname())
        ->all();
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)
            ->not->toContain('SmsGatewayService')
            ->and($src)->not->toContain('ConsentType')
            ->and($src)->not->toContain('App\\Domains\\People\\Models\\')
            ->and($src)->not->toContain('App\\Domains\\Identity\\Models\\')
            ->and($src)->not->toContain('App\\Domains\\Settings\\Models\\');
    }

    $consumers = [
        app_path('Domains/Website/Http/Controllers/PublicSite/PrayerTimesController.php'),
        app_path('Domains/Portal/Actions/ComposeDashboardPrayerAction.php'),
    ];
    foreach ($consumers as $file) {
        $src = file_get_contents($file);
        expect($src)
            ->toContain('PrayerTimeProviderInterface')
            ->and($src)->not->toContain('App\\Domains\\PrayerTimes\\Models\\')
            ->and($src)->not->toContain('IslamicCalendarService::getPrayerTimes');
    }

    $dashboard = file_get_contents(app_path('Domains/Portal/Http/Controllers/DashboardController.php'));
    expect($dashboard)
        ->toContain('ComposeDashboardPrayerAction')
        ->and($dashboard)->not->toContain('IslamicCalendarService::getPrayerTimes');

    expect(config('morph-map.prayer_category'))->toBe(\App\Domains\PrayerTimes\Models\PrayerCategory::class)
        ->and(config('morph-map.prayer_island'))->toBe(PrayerIsland::class)
        ->and(config('morph-map.prayer_time'))->toBe(PrayerTime::class)
        ->and(config('morph-map.prayer_recipient_group'))->toBe(\App\Domains\PrayerTimes\Models\PrayerRecipientGroup::class)
        ->and(config('morph-map.prayer_broadcast'))->toBe(PrayerBroadcast::class)
        ->and(config('morph-map.prayer_broadcast_recipient'))->toBe(PrayerBroadcastRecipient::class);
});
