<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Support\Contracts\QuranReferenceReader;

class ResolveQuranPassageAction
{
    /**
     * @return array{surah: array<string, mixed>, ayah_start: int, ayah_end: int, ayahs: list<array<string, mixed>>}|null
     */
    public function execute(?int $surahId, ?int $ayahStart, ?int $ayahEnd): ?array
    {
        if ($surahId === null) {
            return null;
        }

        $reader = app(QuranReferenceReader::class);
        $surah = $reader->findSurah($surahId);
        if ($surah === null) {
            return null;
        }

        $start = $ayahStart ?? 1;
        $end = $ayahEnd ?? (int) $surah['ayah_count'];
        $ayahs = array_values(array_filter(
            $reader->listAyahs((int) $surah['index']),
            fn (array $ayah): bool => $ayah['ayah_number'] >= $start && $ayah['ayah_number'] <= $end,
        ));

        return [
            'surah' => $surah,
            'ayah_start' => $start,
            'ayah_end' => $end,
            'ayahs' => $ayahs,
        ];
    }
}
