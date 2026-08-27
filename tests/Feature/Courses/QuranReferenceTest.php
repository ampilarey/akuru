<?php

use App\Domains\Courses\Components\Quran\Actions\ListQuranReferenceAction;
use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\Surah;
use App\Support\Contracts\QuranReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedQuranReferenceFixture(): Surah
{
    $surah = Surah::query()->create([
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
        'name' => 'Test mushaf',
        'source_type' => 'manual',
        'is_active' => true,
    ]);

    QuranAyah::query()->create([
        'quran_mushaf_id' => $mushaf->id,
        'surah_number' => 1,
        'ayah_number' => 1,
        'juz_number' => 1,
        'page_number' => 1,
        'text_uthmani' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ',
        'text_simple' => 'بسم الله الرحمن الرحيم',
    ]);

    return $surah;
}

it('lists existing surahs and ayahs through the support contract', function () {
    $surah = seedQuranReferenceFixture();
    $reader = app(QuranReferenceReader::class);

    expect($reader->listSurahs())->toHaveCount(1)
        ->and($reader->findSurah($surah->id)['english_name'])->toBe('Al-Fatihah')
        ->and($reader->findSurah(1)['arabic_name'])->toBe('الفاتحة')
        ->and($reader->listAyahs(1))->toHaveCount(1)
        ->and($reader->listAyahs(1)[0]['text_simple'])->toBe('بسم الله الرحمن الرحيم');

    $payload = app(ListQuranReferenceAction::class)->execute(1);
    expect($payload['selected_surah']['index'])->toBe(1)
        ->and($payload['ayahs'])->toHaveCount(1);
});

it('lets catalog staff browse quran reference and export csv', function () {
    seedQuranReferenceFixture();
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.quran.index', ['surah' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/QuranReference')
            ->has('surahs', 1)
            ->has('ayahs', 1)
            ->where('selected_surah.english_name', 'Al-Fatihah')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.quran.export', ['surah' => 1]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $other = actingPeopleAdmin(['hr.manage']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.quran.index'))
        ->assertForbidden();
});

it('does not let courses php import the hifz namespace', function () {
    $root = base_path('app/Domains/Courses');
    $violations = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (str_contains($contents, 'App\\Domains\\Hifz\\')) {
            $violations[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});
