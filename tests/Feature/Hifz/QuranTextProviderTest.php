<?php

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\Surah;
use App\Support\Contracts\QuranTextProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function seedW21AyahFixture(): QuranAyah
{
    Surah::query()->create([
        'index' => 1,
        'arabic_name' => 'الفاتحة',
        'english_name' => 'Al-Fatihah',
        'transliteration' => 'Al-Fatihah',
        'ayah_count' => 7,
        'revelation_place' => 'Meccan',
        'juz_start' => 1,
        'juz_end' => 1,
        'is_active' => true,
    ]);

    $mushaf = QuranMushaf::query()->create([
        'name' => 'W2.1 mushaf',
        'source_type' => 'manual',
        'is_active' => true,
    ]);

    return QuranAyah::query()->create([
        'quran_mushaf_id' => $mushaf->id,
        'surah_number' => 1,
        'ayah_number' => 1,
        'juz_number' => 1,
        'page_number' => 1,
        'text_uthmani' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ',
        'text_simple' => 'بسم الله الرحمن الرحيم',
    ]);
}

it('imports fixture meanings onto existing quran_ayahs and serves them through the provider', function () {
    $ayah = seedW21AyahFixture();

    $this->artisan('quran:import-translations', [
        'path' => database_path('data/quran_translations/akuru-teaching-gloss-fatiha-1-en.json'),
    ])->assertSuccessful();

    $this->artisan('quran:import-translations', [
        'path' => database_path('data/quran_translations/akuru-teaching-gloss-fatiha-1-dv.json'),
    ])->assertSuccessful();

    $payload = app(QuranTextProviderInterface::class)->ayahWithMeanings(1, 1);

    expect($payload)->not->toBeNull()
        ->and($payload['id'])->toBe($ayah->id)
        ->and($payload['text_uthmani'])->toBe('بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ')
        ->and($payload['meanings']['en'])->toBe('In the name of Allah, the Beneficent, the Merciful.')
        ->and($payload['meanings']['dv'])->toContain('ރަޙްމާން')
        ->and($payload['meaning_source'])->toBe('Akuru teaching gloss (fixture)')
        ->and($payload['surah']['english_name'])->toBe('Al-Fatihah');
});

it('skips translation rows when the ayah does not exist on the mushaf', function () {
    seedW21AyahFixture();

    $this->artisan('quran:import-translations', [
        'path' => database_path('data/quran_translations/akuru-teaching-gloss-fatiha-1-en.json'),
    ])->assertSuccessful();

    $tmp = sys_get_temp_dir().'/w21-missing-ayah.json';
    file_put_contents($tmp, json_encode([
        'source_name' => 'Akuru teaching gloss (fixture)',
        'language' => 'en',
        'ayahs' => [
            ['surah' => 2, 'ayah' => 1, 'text' => 'Alif. Lam. Mim.'],
        ],
    ]));

    $this->artisan('quran:import-translations', ['path' => $tmp])
        ->expectsOutputToContain('skipped 1')
        ->assertSuccessful();

    expect(app(QuranTextProviderInterface::class)->findAyah(2, 1))->toBeNull();
    @unlink($tmp);
});

it('does not add parallel Quran source tables or let the provider contract import Hifz models', function () {
    expect(Schema::hasTable('quran_ayahs'))->toBeTrue()
        ->and(Schema::hasTable('surahs'))->toBeTrue()
        ->and(Schema::hasTable('quran_translations'))->toBeTrue()
        ->and(Schema::hasTable('quran_surahs'))->toBeFalse();

    $contract = file_get_contents(app_path('Support/Contracts/QuranTextProviderInterface.php'));
    expect($contract)->not->toContain('App\\Domains\\Hifz\\')
        ->and($contract)->not->toContain('App\\Domains\\Website\\');
});
