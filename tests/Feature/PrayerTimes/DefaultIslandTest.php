<?php

use App\Domains\Portal\Actions\ComposeDashboardPrayerAction;
use App\Domains\PrayerTimes\Actions\ResolveDefaultPrayerIslandAction;
use App\Domains\PrayerTimes\Models\PrayerCategory;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Which island's prayer times to show when nobody has chosen one.
 *
 * Four places answered this differently and the one written to be the single
 * answer was called by nothing. Two consequences were real: `listIslands()`
 * orders by atoll, so the readers' "first island" was the alphabetically-first
 * atoll and never Malé; and neither reader validated the configured id, so a
 * deleted or deactivated island silently blanked prayer times instead of
 * falling back.
 */
function island(string $nameLatin, string $atoll, bool $active = true, ?string $dhivehi = null): PrayerIsland
{
    // Islands belong to a B&G prayer category (the timing group); any one will
    // do here, so reuse a single row rather than making the fixture about it.
    $categoryId = PrayerCategory::query()->first()?->id
        ?? PrayerCategory::query()->create([])->id;

    return PrayerIsland::query()->create([
        'category_id' => $categoryId,
        'atoll' => $atoll,
        'name' => $dhivehi ?? $nameLatin,
        'name_latin' => $nameLatin,
        'atoll_latin' => $atoll,
        'latitude' => 4.17,
        'longitude' => 73.5,
        'is_active' => $active,
    ]);
}

it('prefers Male over the alphabetically first atoll', function () {
    // Addu sorts before Malé by atoll, which is exactly what the old readers
    // picked. Another atoll's times are wrong by minutes, and for prayer times
    // minutes are the whole point.
    island('Hithadhoo', 'Addu');
    $male = island('Malé', 'Kaafu');

    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $male->id);
});

it('finds Male by its dhivehi name too', function () {
    island('Hithadhoo', 'Addu');
    // The importer knew it as މާލެ and the action knew it as Malé, so each
    // found it only on datasets the other would have missed.
    $male = island('Male City', 'Kaafu', true, 'މާލެ');

    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $male->id);
});

it('honours an island somebody actually chose', function () {
    island('Malé', 'Kaafu');
    $chosen = island('Kulhudhuffushi', 'Haa Dhaalu');
    app(SetSettingAction::class)->execute('prayer.default_island_id', (string) $chosen->id, 'string', 'prayer', 'x');

    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $chosen->id);
});

it('falls back when the chosen island has been deleted', function () {
    $male = island('Malé', 'Kaafu');
    $gone = island('Temporary', 'Zzz');
    app(SetSettingAction::class)->execute('prayer.default_island_id', (string) $gone->id, 'string', 'prayer', 'x');
    $gone->delete();

    // Previously this left resolveForIsland() holding a dead id and prayer
    // times silently disappeared.
    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $male->id);
});

it('falls back when the chosen island has been deactivated', function () {
    $male = island('Malé', 'Kaafu');
    $off = island('Retired', 'Zzz', false);
    app(SetSettingAction::class)->execute('prayer.default_island_id', (string) $off->id, 'string', 'prayer', 'x');

    // A deactivated island is as unusable as a deleted one: no current times.
    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $male->id);
});

it('falls back to the first active island when there is no Male', function () {
    $first = island('Hithadhoo', 'Addu');
    island('Fuvahmulah', 'Gnaviyani');

    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $first->id);
});

it('never picks an inactive island', function () {
    island('Retired', 'Addu', false);
    $active = island('Hithadhoo', 'Baa');

    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $active->id);
});

it('answers null when there are no islands at all', function () {
    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBeNull();
});

it('gives the dashboard the same island as everything else', function () {
    island('Hithadhoo', 'Addu');
    $male = island('Malé', 'Kaafu');

    // The dashboard used to disagree with the importer about which island the
    // school is on.
    $payload = app(ComposeDashboardPrayerAction::class)->execute();

    expect(app(ResolveDefaultPrayerIslandAction::class)->execute())->toBe((int) $male->id)
        ->and($payload)->toHaveKeys(['islamicDate', 'prayerTimes', 'currentPrayer', 'specialDays']);
});
