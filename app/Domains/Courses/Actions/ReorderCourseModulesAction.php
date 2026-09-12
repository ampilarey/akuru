<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §12 Module Management: **"Reorder modules"**.
 *
 * There was no reorder of any kind. `position` was assigned once at creation
 * as `max(position) + 1` and never changed again, so the order a course's
 * modules were typed in was the order students saw them in, permanently. An
 * author who added Unit 3 before realising Unit 2 was missing had no way to
 * fix it short of deleting and retyping — and §12's delete only works on an
 * empty draft module, so once a module had a lesson the order was frozen.
 *
 * The same shape as `ReorderContentBlocksAction` from the §16 slice, and for
 * the same reason: the incoming list must name this course's modules exactly
 * once each, so a partial or foreign list cannot renumber some rows and leave
 * the rest colliding.
 */
class ReorderCourseModulesAction
{
    /**
     * @param  list<int>  $orderedIds
     */
    public function execute(int $courseId, array $orderedIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $orderedIds)));

        $existing = CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('position')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        sort($ids);
        $expected = $existing;
        sort($expected);

        if ($ids !== $expected) {
            throw ValidationException::withMessages([
                'order' => 'The new order must list every module of this course exactly once.',
            ]);
        }

        DB::transaction(function () use ($courseId, $orderedIds): void {
            foreach (array_values(array_unique(array_map('intval', $orderedIds))) as $position => $id) {
                CourseModule::query()
                    ->where('course_id', $courseId)
                    ->where('id', $id)
                    ->update(['position' => $position]);
            }
        });
    }
}
