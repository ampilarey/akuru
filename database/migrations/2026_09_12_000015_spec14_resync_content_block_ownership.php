<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC §14: `content_blocks.course_id` and `course_module_id` are denormalized
 * from `lesson_id`, and "must be synced automatically" whenever a lesson
 * moves. Until this slice nothing synced them, so any lesson that was ever
 * moved between modules left its blocks pointing at the module it used to be
 * in.
 *
 * The observer added alongside this keeps them right from now on. This repairs
 * whatever drifted before it existed.
 *
 * Safe by construction, and worth saying why rather than assuming it:
 *
 *   - It only ever **recomputes a denormalized value from its own source of
 *     truth**. `lesson_id` is not touched, and no row is created or deleted.
 *     Nothing here can lose information, because everything it writes is
 *     already derivable from what it reads.
 *   - It is idempotent — running it twice changes nothing the second time.
 *   - It touches only rows that actually disagree, so on a deployment where
 *     no lesson ever moved it is a no-op.
 *
 * That is why this is a data migration rather than a rule-9 three-deploy
 * dance: rule 9 guards against dropping or renaming a populated column, and
 * this drops nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stale = DB::table('content_blocks')
            ->join('lessons', 'lessons.id', '=', 'content_blocks.lesson_id')
            ->where(function ($query) {
                $query->whereColumn('content_blocks.course_id', '!=', 'lessons.course_id')
                    ->orWhereColumn('content_blocks.course_module_id', '!=', 'lessons.course_module_id');
            })
            ->count();

        if ($stale === 0) {
            return;
        }

        DB::statement('
            UPDATE content_blocks
            JOIN lessons ON lessons.id = content_blocks.lesson_id
            SET content_blocks.course_id = lessons.course_id,
                content_blocks.course_module_id = lessons.course_module_id,
                content_blocks.updated_at = NOW()
            WHERE content_blocks.course_id <> lessons.course_id
               OR content_blocks.course_module_id <> lessons.course_module_id
        ');
    }

    public function down(): void
    {
        // Deliberately irreversible. The "previous" values were wrong by
        // definition — they disagreed with the lesson that owns the block —
        // and they are not recorded anywhere to restore from. Reversing this
        // would mean re-introducing known-bad data.
    }
};
