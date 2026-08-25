<?php

namespace App\Domains\Courses\Actions;

use App\Support\Contracts\QuranReferenceReader;

class ListQuranReferenceAction
{
    /**
     * @return array{surahs: list<array<string, mixed>>, selected_surah: array<string, mixed>|null, ayahs: list<array<string, mixed>>}
     */
    public function execute(?int $surahNumber = null): array
    {
        $reader = app(QuranReferenceReader::class);
        $selected = $surahNumber ? $reader->findSurah($surahNumber) : null;
        $ayahSurah = $selected['index'] ?? $surahNumber;

        return [
            'surahs' => $reader->listSurahs(),
            'selected_surah' => $selected,
            'ayahs' => $ayahSurah ? $reader->listAyahs((int) $ayahSurah) : [],
        ];
    }
}
