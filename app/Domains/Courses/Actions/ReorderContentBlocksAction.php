<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;

class ReorderContentBlocksAction
{
    /**
     * @param  list<int>  $blockIds
     */
    public function execute(int $lessonId, array $blockIds): void
    {
        foreach (array_values($blockIds) as $position => $id) {
            ContentBlock::query()
                ->where('lesson_id', $lessonId)
                ->where('id', $id)
                ->update(['position' => $position]);
        }
    }
}
