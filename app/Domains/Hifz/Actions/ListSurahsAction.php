<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\Surah;

class ListSurahsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(bool $activeOnly = false): array
    {
        return Surah::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('index')
            ->get()
            ->map(fn (Surah $surah): array => $this->toArray($surah))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $idOrIndex): ?array
    {
        $surah = Surah::query()->find($idOrIndex)
            ?? Surah::query()->where('index', $idOrIndex)->first();

        return $surah ? $this->toArray($surah) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Surah $surah): array
    {
        return [
            'id' => $surah->id,
            'index' => (int) $surah->index,
            'arabic_name' => $surah->arabic_name,
            'english_name' => $surah->english_name,
            'transliteration' => $surah->transliteration,
            'ayah_count' => (int) $surah->ayah_count,
            'revelation_place' => $surah->revelation_place,
            'juz_start' => $surah->juz_start ? (int) $surah->juz_start : null,
            'juz_end' => $surah->juz_end ? (int) $surah->juz_end : null,
            'is_active' => (bool) $surah->is_active,
        ];
    }
}
