<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Enums\QuranTranslationLanguage;
use App\Domains\Hifz\Models\QuranTranslation;
use App\Support\Contracts\QuranTextProviderInterface;

class ReadQuranTextAction implements QuranTextProviderInterface
{
    public function __construct(
        private readonly ListSurahsAction $surahs,
        private readonly ListAyahsAction $ayahs,
    ) {}

    public function findSurah(int $idOrIndex): ?array
    {
        return $this->surahs->find($idOrIndex);
    }

    public function findAyah(int $surahNumber, int $ayahNumber, ?int $mushafId = null): ?array
    {
        foreach ($this->ayahs->execute($surahNumber, $mushafId) as $ayah) {
            if ((int) $ayah['ayah_number'] === $ayahNumber) {
                return $ayah;
            }
        }

        return null;
    }

    public function translation(int $quranAyahId, string $language, ?string $sourceName = null): ?array
    {
        if (QuranTranslationLanguage::tryFrom($language) === null) {
            return null;
        }

        $query = QuranTranslation::query()
            ->where('quran_ayah_id', $quranAyahId)
            ->where('language', $language);

        $source = $sourceName ?? (string) config('quran.translation_source');
        if ($source !== '') {
            $query->where('source_name', $source);
        }

        $row = $query->orderBy('id')->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'quran_ayah_id' => (int) $row->quran_ayah_id,
            'language' => $row->language instanceof QuranTranslationLanguage ? $row->language->value : (string) $row->language,
            'text' => $row->text,
            'source_name' => $row->source_name,
            'source_note' => $row->source_note,
        ];
    }

    public function ayahWithMeanings(int $surahNumber, int $ayahNumber, ?string $sourceName = null, ?int $mushafId = null): ?array
    {
        $ayah = $this->findAyah($surahNumber, $ayahNumber, $mushafId);
        if ($ayah === null) {
            return null;
        }

        $en = $this->translation((int) $ayah['id'], QuranTranslationLanguage::English->value, $sourceName);
        $dv = $this->translation((int) $ayah['id'], QuranTranslationLanguage::Dhivehi->value, $sourceName);

        return [
            ...$ayah,
            'surah' => $this->findSurah($surahNumber),
            'meanings' => [
                'en' => $en['text'] ?? null,
                'dv' => $dv['text'] ?? null,
            ],
            'meaning_source' => $en['source_name'] ?? $dv['source_name'] ?? null,
        ];
    }
}
