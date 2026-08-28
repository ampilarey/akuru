<?php

use App\Domains\PrayerTimes\Actions\ImportPrayerTimesFromSalatDbAction;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerTime;
use App\Domains\Settings\Actions\GetSettingAction;
use Database\Seeders\PrayerTimesDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRealShapedSalatDb(): string
{
    $path = tempnam(sys_get_temp_dir(), 'salat').'.db';
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE Category (Id INTEGER)');
    $pdo->exec('CREATE TABLE Island (CategoryId INTEGER, IslandId INTEGER, Atoll TEXT, Island TEXT, Minutes INTEGER, Latitude REAL, Longitude REAL, Status INTEGER)');
    $pdo->exec('CREATE TABLE PrayerTimes (CategoryId INTEGER, Date INTEGER, Fajuru INTEGER, Sunrise INTEGER, Dhuhr INTEGER, Asr INTEGER, Maghrib INTEGER, Isha INTEGER)');

    $pdo->exec('INSERT INTO Category VALUES (57)');
    $pdo->exec("INSERT INTO Island VALUES (57, 102, 'ކ', 'މާލެ', 0, 4.1755, 73.5093, 1)");
    $pdo->exec("INSERT INTO Island VALUES (57, 7, 'ހއ', 'ބެރިންމަދޫ', 1, NULL, NULL, 0)");

    $insert = $pdo->prepare('INSERT INTO PrayerTimes VALUES (57, ?, ?, ?, ?, ?, ?, ?)');
    for ($date = 0; $date <= 365; $date++) {
        $insert->execute([$date, 300 + ($date % 5), 377, 735, 934, 1085, 1163]);
    }

    return $path;
}

it('imports the real Bake&Grill column shape: IslandId, Island, Status, Fajuru, 0-based Date', function () {
    $path = makeRealShapedSalatDb();
    $counts = app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);
    unlink($path);

    expect($counts)->toBe(['categories' => 1, 'islands' => 2, 'times' => 366]);

    // 0-based Date normalized to this app's 1–366 day_of_year.
    expect(PrayerTime::query()->where('day_of_year', 0)->exists())->toBeFalse()
        ->and(PrayerTime::query()->where('day_of_year', 1)->value('fajr'))->toBe(300)
        ->and(PrayerTime::query()->where('day_of_year', 366)->exists())->toBeTrue();

    // Island columns land, latin names backfill from the curated map,
    // and Status 0 stays inactive.
    $male = PrayerIsland::query()->find(102);
    expect($male->name)->toBe('މާލެ')
        ->and($male->name_latin)->toBe('Malé')
        ->and($male->atoll_latin)->toBe('Kaafu')
        ->and((bool) $male->is_active)->toBeTrue();
    $inactive = PrayerIsland::query()->find(7);
    expect($inactive->name_latin)->toBe('Berinmadhoo')
        ->and((bool) $inactive->is_active)->toBeFalse();
});

it('lets an admin import the bundled dataset in one click with Malé defaulted', function () {
    $admin = actingPeopleAdmin(['prayer.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.prayer-times.import.store'), ['use_bundled' => '1'])
        ->assertSessionHasNoErrors();

    expect(PrayerIsland::query()->count())->toBe(205);
    $defaultId = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', '0');
    expect(PrayerIsland::query()->find($defaultId)?->name)->toBe('މާލެ');
});

it('serves the islands API and renders the header prayer banner on the public site', function () {
    $path = makeRealShapedSalatDb();
    app(ImportPrayerTimesFromSalatDbAction::class)->execute($path);
    unlink($path);

    // Active islands only, in the DTO shape the banner script consumes.
    $this->getJson('/api/v1/prayer-times/islands')
        ->assertOk()
        ->assertJsonCount(1, 'islands')
        ->assertJsonPath('islands.0.id', 102)
        ->assertJsonPath('islands.0.name_en', 'Malé')
        ->assertJsonPath('islands.0.atoll_latin', 'Kaafu');

    // Banner appears twice (desktop header + mobile strip); assets once.
    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->getContent();
    // Count the section markup, not the bare attribute name — the banner
    // script also mentions data-pt-banner inside a querySelector string.
    expect(substr_count($html, '<section class="prayer-banner'))->toBe(2)
        ->and(substr_count($html, 'id="hptPanel"'))->toBe(1);
});

it('seeds the committed salat.db end-to-end and defaults the island to Malé', function () {
    expect(is_file(database_path('salat.db')))->toBeTrue();

    $this->seed(PrayerTimesDatabaseSeeder::class);

    expect(PrayerIsland::query()->count())->toBe(205)
        ->and(PrayerTime::query()->count())->toBe(15372)
        ->and(PrayerIsland::query()->where('is_active', true)->count())->toBe(188);

    $defaultId = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', '0');
    expect(PrayerIsland::query()->find($defaultId)?->name)->toBe('މާލެ');

    // The real dataset replaced the synthetic fixture: Malé's Fajr on
    // Jan 1 is an early-morning value, not the synthetic 09:00 (540).
    $male = PrayerIsland::query()->find($defaultId);
    $fajr = (int) PrayerTime::query()
        ->where('category_id', $male->category_id)
        ->where('day_of_year', 1)
        ->value('fajr');
    expect($fajr)->toBeGreaterThan(240)->toBeLessThan(360);
});
