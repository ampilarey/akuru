<?php

namespace App\Domains\Pronunciation\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Letter/haraka pickers for the practice surface — table-level reads only
 * (rule 3: no cross-domain model imports; rule 4 keeps DB:: out of
 * controllers).
 */
class ListArabicSoundReferencesAction
{
    /**
     * @return array{letters: list<array<string, mixed>>, harakas: list<array<string, mixed>>}
     */
    public function execute(): array
    {
        return [
            'letters' => DB::table('arabic_letters')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'key_name', 'arabic_character'])
                ->map(fn ($row) => ['id' => $row->id, 'key_name' => $row->key_name, 'char' => $row->arabic_character])
                ->all(),
            'harakas' => DB::table('arabic_harakas')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'key_name', 'symbol'])
                ->map(fn ($row) => ['id' => $row->id, 'key_name' => $row->key_name, 'symbol' => $row->symbol])
                ->all(),
        ];
    }
}
