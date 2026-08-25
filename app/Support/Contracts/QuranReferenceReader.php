<?php

namespace App\Support\Contracts;

interface QuranReferenceReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listSurahs(bool $activeOnly = false): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findSurah(int $idOrIndex): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listAyahs(int $surahNumber, ?int $mushafId = null): array;
}
