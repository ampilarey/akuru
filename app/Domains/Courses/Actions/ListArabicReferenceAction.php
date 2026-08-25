<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ArabicHarakah;
use App\Domains\Courses\Models\ArabicLetter;
use Illuminate\Support\Collection;

class ListArabicReferenceAction
{
    /**
     * @return array{letters: Collection<int, array<string, mixed>>, harakas: Collection<int, array<string, mixed>>}
     */
    public function execute(bool $activeOnly = false): array
    {
        $letters = ArabicLetter::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ArabicLetter $row): array => [
                'id' => $row->id,
                'key_name' => $row->key_name,
                'arabic_character' => $row->arabic_character,
                'display_name' => $row->display_name,
                'description' => $row->description,
                'sort_order' => $row->sort_order,
                'is_active' => (bool) $row->is_active,
            ]);

        $harakas = ArabicHarakah::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ArabicHarakah $row): array => [
                'id' => $row->id,
                'key_name' => $row->key_name,
                'symbol' => $row->symbol,
                'display_name' => $row->display_name,
                'description' => $row->description,
                'sort_order' => $row->sort_order,
                'is_active' => (bool) $row->is_active,
            ]);

        return [
            'letters' => $letters->values(),
            'harakas' => $harakas->values(),
        ];
    }
}
