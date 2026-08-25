<?php

namespace App\Domains\ExamsGrades\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncStandardTagsAction
{
    /**
     * @param  list<int>  $standardIds
     */
    public function execute(string $taggableType, int $taggableId, array $standardIds): void
    {
        if (! Schema::hasTable('standard_taggables')) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $standardIds))));

        DB::table('standard_taggables')
            ->where('taggable_type', $taggableType)
            ->where('taggable_id', $taggableId)
            ->delete();

        foreach ($ids as $standardId) {
            if ($standardId < 1) {
                continue;
            }
            DB::table('standard_taggables')->insert([
                'standard_id' => $standardId,
                'taggable_type' => $taggableType,
                'taggable_id' => $taggableId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
