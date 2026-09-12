<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use Illuminate\Support\Facades\DB;

class DuplicateContentBlockAction
{
    /**
     * SPEC §16: "Duplicating blocks where safe."
     *
     * Every current block type is safe to duplicate, and it is worth saying
     * why rather than leaving it implied:
     *
     *  - The outline is the **working copy**. Students read the published
     *    revision snapshot (`ResolvePublishedLessonAction`), so adding a block
     *    to a published lesson changes nothing for anyone until the author
     *    publishes again. There is no live content to damage.
     *  - A media block holds a `media_id`, and deleting a block never deletes
     *    the file it points at. Two blocks sharing one upload is therefore an
     *    ordinary state, not a dangling reference waiting to happen — and
     *    `ServeCatalogMediaAction` authorizes by whether the id appears in any
     *    published revision, which a second reference cannot weaken.
     *
     * The copy lands **immediately after** the original rather than at the end
     * of the lesson. Duplicating is how an author builds a run of similar
     * blocks; dropping the copy at the bottom would mean a reorder every time.
     */
    public function execute(ContentBlock $block): ContentBlock
    {
        return DB::transaction(function () use ($block): ContentBlock {
            $position = (int) $block->position;

            // Open the gap first, so the copy has somewhere to land without
            // colliding with the block that currently sits there.
            ContentBlock::query()
                ->where('lesson_id', $block->lesson_id)
                ->where('position', '>', $position)
                ->increment('position');

            return ContentBlock::query()->create([
                'lesson_id' => $block->lesson_id,
                'type' => $block->type,
                'position' => $position + 1,
                'title' => $block->title,
                'data' => $block->data,
                'settings' => $block->settings,
                'is_required' => (bool) $block->is_required,
                'created_by' => $block->created_by,
            ]);
        });
    }
}
