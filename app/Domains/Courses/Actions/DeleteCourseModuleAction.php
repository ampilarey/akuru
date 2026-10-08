<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §12: "Course creators must be able to … **Delete draft modules if
 * safe**."
 *
 * There was no module delete anywhere — no route, no controller method, no
 * action. A module added by mistake was permanent, while the blocks inside it
 * could be deleted freely.
 *
 * The foreign keys are already on the right side of this: `lessons`,
 * `content_blocks` and `student_lesson_progress` all point at
 * `course_modules` with **ON DELETE RESTRICT**, the opposite of the course
 * cascade §29 had to fix. The database would refuse an unsafe delete on its
 * own. What it would not do is explain itself: a RESTRICT violation reaches the
 * admin as a 500 and an SQL string. So the dependents are counted first and the
 * refusal says what is in the way.
 *
 * Two conditions, both from §12's own wording:
 *
 *   - **draft** — a published module is part of a course people are taking.
 *   - **safe** — nothing of anyone's hangs off it.
 */
class DeleteCourseModuleAction
{
    /**
     * Tables that make a module part of somebody's course. Each is named in
     * the refusal by `teach.module_holds_<table>`, in the page's language
     * (slice CT6b-2b).
     *
     * @var array<string, string> table => column
     */
    private const DEPENDENTS = [
        'lessons' => 'course_module_id',
        'content_blocks' => 'course_module_id',
        'student_lesson_progress' => 'course_module_id',
    ];

    /**
     * @throws ValidationException
     */
    public function execute(CourseModule $module): void
    {
        $status = $module->status instanceof \BackedEnum ? $module->status->value : (string) $module->status;

        if ($status !== 'draft') {
            throw ValidationException::withMessages([
                'module' => __('teach.error_module_not_draft'),
            ]);
        }

        $counts = $this->dependentCounts($module);

        if ($counts !== []) {
            $parts = [];
            foreach ($counts as $table => $count) {
                $parts[] = trans_choice('teach.module_holds_'.$table, $count, ['count' => $count]);
            }

            throw ValidationException::withMessages([
                'module' => __('teach.error_module_holds', ['things' => implode(__('teach.list_joiner'), $parts)]),
            ]);
        }

        // Soft: `course_modules` carries `deleted_at` and the model uses
        // SoftDeletes, so an empty draft module leaves the outline without the
        // row going anywhere.
        $module->delete();
    }

    /**
     * @return array<string, int> table => count, only for tables that exist and have rows
     */
    public function dependentCounts(CourseModule $module): array
    {
        $counts = [];

        foreach (self::DEPENDENTS as $table => $column) {
            // Checked for existence because this list spans phases and a
            // database part-way through migrations should not fatal here.
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = DB::table($table)->where($column, $module->id);

            // Soft-deleted children are not in anybody's way.
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $count = (int) $query->count();
            if ($count > 0) {
                $counts[$table] = $count;
            }
        }

        return $counts;
    }
}
