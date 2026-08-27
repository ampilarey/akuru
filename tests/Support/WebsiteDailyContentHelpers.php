<?php

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\Surah;

function w22Ayah(): QuranAyah
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
        'name' => 'W2.2 mushaf',
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

function w22ImportMeanings(): void
{
    test()->artisan('quran:import-translations', [
        'path' => database_path('data/quran_translations/akuru-teaching-gloss-fatiha-1-en.json'),
    ])->assertSuccessful();

    test()->artisan('quran:import-translations', [
        'path' => database_path('data/quran_translations/akuru-teaching-gloss-fatiha-1-dv.json'),
    ])->assertSuccessful();
}
