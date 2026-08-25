<?php

namespace App\Domains\ExamsGrades\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListStandardTagsAction
{
    /**
     * @return Collection<int, int>
     */
    public function execute(string $taggableType, int $taggableId): Collection
    {
        if (! Schema::hasTable('standard_taggables')) {
            return collect();
        }

        return collect(DB::table('standard_taggables')
            ->where('taggable_type', $taggableType)
            ->where('taggable_id', $taggableId)
            ->pluck('standard_id'))
            ->map(fn ($id): int => (int) $id)
            ->values();
    }
}
