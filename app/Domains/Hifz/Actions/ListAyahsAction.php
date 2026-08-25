<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\QuranAyah;
use App\Domains\Hifz\Models\QuranMushaf;

class ListAyahsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $surahNumber, ?int $mushafId = null): array
    {
        $resolvedMushafId = $mushafId ?? QuranMushaf::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        $query = QuranAyah::query()
            ->where('surah_number', $surahNumber)
            ->orderBy('ayah_number');

        if ($resolvedMushafId) {
            $query->where('quran_mushaf_id', $resolvedMushafId);
        }

        return $query->get()
            ->map(fn (QuranAyah $ayah): array => [
                'id' => $ayah->id,
                'surah_number' => (int) $ayah->surah_number,
                'ayah_number' => (int) $ayah->ayah_number,
                'text_uthmani' => $ayah->text_uthmani,
                'text_simple' => $ayah->text_simple,
                'juz_number' => $ayah->juz_number ? (int) $ayah->juz_number : null,
                'page_number' => $ayah->page_number ? (int) $ayah->page_number : null,
            ])
            ->values()
            ->all();
    }
}
