<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Enums\QuranTranslationLanguage;
use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Models\QuranTranslation;

class ImportQuranTranslationsAction
{
    /**
     * Upsert a licensed or fixture translation file onto existing quran_ayahs.
     *
     * @param  array{
     *     source_name: string,
     *     source_note?: ?string,
     *     language: string,
     *     ayahs: list<array{surah: int, ayah: int, text: string}>
     * }  $payload
     * @return array{imported: int, skipped: int, source_name: string, language: string}
     */
    public function execute(array $payload, ?int $mushafId = null): array
    {
        $language = QuranTranslationLanguage::tryFrom((string) ($payload['language'] ?? ''));
        $sourceName = trim((string) ($payload['source_name'] ?? ''));
        if ($language === null || $sourceName === '') {
            throw new \InvalidArgumentException('Translation import requires language (en|dv|ar_tafsir) and source_name.');
        }

        $resolvedMushafId = $mushafId ?? QuranMushaf::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        $imported = 0;
        $skipped = 0;
        $note = isset($payload['source_note']) ? trim((string) $payload['source_note']) : null;
        $note = $note === '' ? null : $note;

        foreach ($payload['ayahs'] ?? [] as $row) {
            $surah = (int) ($row['surah'] ?? 0);
            $ayahNumber = (int) ($row['ayah'] ?? 0);
            $text = trim((string) ($row['text'] ?? ''));
            if ($surah <= 0 || $ayahNumber <= 0 || $text === '') {
                $skipped++;

                continue;
            }

            $query = QuranAyah::query()
                ->where('surah_number', $surah)
                ->where('ayah_number', $ayahNumber);
            if ($resolvedMushafId) {
                $query->where('quran_mushaf_id', $resolvedMushafId);
            }
            $ayah = $query->first();
            if ($ayah === null) {
                $skipped++;

                continue;
            }

            QuranTranslation::query()->updateOrCreate(
                [
                    'quran_ayah_id' => $ayah->id,
                    'language' => $language,
                    'source_name' => $sourceName,
                ],
                [
                    'text' => $text,
                    'source_note' => $note,
                ],
            );
            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'source_name' => $sourceName,
            'language' => $language->value,
        ];
    }
}
