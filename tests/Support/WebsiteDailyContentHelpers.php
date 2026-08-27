<?php

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\Surah;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;

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

function w23Published(array $overrides = []): DailyContent
{
    $maker = actingPeopleAdmin(['daily_content.manage']);
    $checker = actingPeopleAdmin(['daily_content.approve']);

    return DailyContent::query()->create(array_merge([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-08-27',
        'status' => DailyContentStatus::Published->value,
        'text_en' => 'Seek knowledge.',
        'text_dv' => 'އިލްމު ހޯދާ',
        'attribution' => 'Teaching note',
        'created_by' => $maker->id,
        'approved_by' => $checker->id,
    ], $overrides));
}

function w23PublishedAyah(string $date = '2026-08-27'): DailyContent
{
    $ayah = w22Ayah();
    w22ImportMeanings();

    return w23Published([
        'content_type' => DailyContentType::Ayah->value,
        'publish_date' => $date,
        'quran_ayah_id' => $ayah->id,
        'text_en' => null,
        'text_dv' => null,
        'attribution' => null,
    ]);
}

function w23PublishedHadith(string $date = '2026-08-28'): DailyContent
{
    return w23Published([
        'content_type' => DailyContentType::Hadith->value,
        'publish_date' => $date,
        'hadith_text_ar' => 'إنما الأعمال بالنيات',
        'hadith_text_en' => 'Actions are by intentions.',
        'hadith_text_dv' => 'ޢަމަލުތައް ނިޔަތުގެ މައްޗަށް',
        'hadith_collection' => 'Bukhari',
        'hadith_number' => '1',
        'hadith_grading' => 'sahih',
        'grading_source' => 'Sahih al-Bukhari',
        'text_en' => null,
        'text_dv' => null,
        'attribution' => null,
    ]);
}
