<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderContentBlocksAction
{
    /**
     * SPEC §16: "Reordering must persist correctly."
     *
     * The old version wrote `position = array index` for whatever ids it was
     * handed, one UPDATE at a time, and checked nothing. Two ways that failed:
     *
     *  - A **partial** list renumbered only the ids given, leaving the omitted
     *    blocks on their original positions. Send [c, b] for a lesson holding
     *    [a, b, c] and all three end up at position 0 or 1 — the order the
     *    player then shows is whatever the database returns for a tie.
     *  - An id belonging to **another lesson** was silently ignored, so a
     *    reorder built from stale client state half-applied: some blocks moved,
     *    some did not, and the caller was told it succeeded.
     *
     * Now the list must name the lesson's blocks exactly — same set, no
     * duplicates, nothing missing — and the whole renumbering is one
     * transaction, so a rejected reorder leaves the lesson exactly as it was.
     *
     * @param  list<int>  $blockIds
     *
     * @throws ValidationException
     */
    public function execute(int $lessonId, array $blockIds): void
    {
        $given = array_values(array_map('intval', $blockIds));
        $existing = ContentBlock::query()
            ->where('lesson_id', $lessonId)
            ->orderBy('position')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        sort($given);
        $sortedExisting = $existing;
        sort($sortedExisting);

        if ($given !== $sortedExisting) {
            throw ValidationException::withMessages([
                'block_ids' => 'The new order must list this lesson\'s blocks exactly once each.',
            ]);
        }

        DB::transaction(function () use ($lessonId, $blockIds): void {
            foreach (array_values(array_map('intval', $blockIds)) as $position => $id) {
                ContentBlock::query()
                    ->where('lesson_id', $lessonId)
                    ->where('id', $id)
                    ->update(['position' => $position]);
            }
        });
    }
}
