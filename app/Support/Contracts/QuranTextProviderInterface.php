<?php

namespace App\Support\Contracts;

interface QuranTextProviderInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findSurah(int $idOrIndex): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findAyah(int $surahNumber, int $ayahNumber, ?int $mushafId = null): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function translation(int $quranAyahId, string $language, ?string $sourceName = null): ?array;

    /**
     * Arabic ayah plus attached meanings. Consumers must not query Quran tables.
     *
     * @return array<string, mixed>|null
     */
    public function ayahWithMeanings(int $surahNumber, int $ayahNumber, ?string $sourceName = null, ?int $mushafId = null): ?array;
}
