<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\Lesson;

/**
 * SPEC §14 "Content Block Ownership" is unusually explicit about this:
 *
 *   > Content blocks belong to lessons. `lesson_id` is the source of truth.
 *   >
 *   > `course_id` and `module_id` on `content_blocks` are denormalized for
 *   > query performance only.
 *   >
 *   > Whenever a lesson is moved to another module or course, the related
 *   > `content_blocks.course_id` and `content_blocks.module_id` must be
 *   > **synced automatically**. Use a model observer or service method to
 *   > guarantee this.
 *   >
 *   > **No code may rely on `content_blocks.course_id` or
 *   > `content_blocks.module_id` unless this sync guarantee exists.**
 *
 * The guarantee did not exist. Blocks took their `course_id` and
 * `course_module_id` from the lesson at creation and never looked again, so
 * moving a lesson between modules left every one of its blocks pointing at the
 * module it used to be in. Verified before fixing: a lesson moved from module
 * 1 to module 2 left its block on module 1.
 *
 * And code **did** rely on it, which is the part §14's last sentence forbids.
 * `DeleteCourseModuleAction` implements §12's "Delete draft modules if safe"
 * by counting dependents, and `content_blocks.course_module_id` is one of the
 * three tables it counts. With stale ids that check is wrong in both
 * directions: a module keeps blocks that moved *out* of it, so it looks unsafe
 * to delete forever; and blocks that moved *in* are not counted, so a module
 * can be deleted while a lesson's content still hangs off it — which the
 * database then refuses with a RESTRICT violation the admin sees as a 500.
 *
 * Recomputing from `lesson_id` is idempotent, which is what makes it safe to
 * run from an observer on every lesson save.
 */
class SyncContentBlockOwnershipAction
{
    /**
     * Point a lesson's blocks at the course and module the lesson is in now.
     *
     * @return int rows actually changed
     */
    public function execute(Lesson $lesson): int
    {
        return ContentBlock::query()
            ->where('lesson_id', $lesson->id)
            ->where(function ($query) use ($lesson) {
                $query->where('course_id', '!=', $lesson->course_id)
                    ->orWhere('course_module_id', '!=', $lesson->course_module_id)
                    ->orWhereNull('course_id')
                    ->orWhereNull('course_module_id');
            })
            ->update([
                'course_id' => $lesson->course_id,
                'course_module_id' => $lesson->course_module_id,
                'updated_at' => now(),
            ]);
    }

    /**
     * Whether any block currently disagrees with its lesson.
     *
     * The question §14's guarantee exists to keep answerable as "none", and
     * the one a verification script would ask.
     */
    public function staleCount(): int
    {
        return ContentBlock::query()
            ->join('lessons', 'lessons.id', '=', 'content_blocks.lesson_id')
            ->where(function ($query) {
                $query->whereColumn('content_blocks.course_id', '!=', 'lessons.course_id')
                    ->orWhereColumn('content_blocks.course_module_id', '!=', 'lessons.course_module_id');
            })
            ->count();
    }
}
