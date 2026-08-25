<?php

namespace App\Domains\Hifz\Actions;

use App\Support\Contracts\QuranReferenceReader;

class ReadQuranReferenceAction implements QuranReferenceReader
{
    public function __construct(
        private readonly ListSurahsAction $surahs,
        private readonly ListAyahsAction $ayahs,
    ) {}

    public function listSurahs(bool $activeOnly = false): array
    {
        return $this->surahs->execute($activeOnly);
    }

    public function findSurah(int $idOrIndex): ?array
    {
        return $this->surahs->find($idOrIndex);
    }

    public function listAyahs(int $surahNumber, ?int $mushafId = null): array
    {
        return $this->ayahs->execute($surahNumber, $mushafId);
    }
}
