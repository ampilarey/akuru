<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * The recovery list for courses removed by `DeleteCourseAction` (SPEC §29).
 *
 * #272 made Delete safe: a course with a roster, attempts or payment line items
 * is soft-deleted rather than cascaded away, because
 * `course_enrollments.course_id` and `payment_items.course_id` both CASCADE and
 * rule 12 says ledgers are append-only. It did not make Delete **reversible**.
 * Nothing in the app called `withTrashed()`, `onlyTrashed()` or `restore()`, so
 * the course left every screen and could not be seen or brought back short of a
 * database client.
 *
 * Note this is **not** the catalogue's Archive, which is a workflow state
 * (`workflow_status = 'archived'`) set from the Catalog screen and reversed the
 * same way. An archived course is an ordinary visible row; these are rows the
 * ordinary queries no longer return at all.
 */
class ListDeletedCoursesAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $courses = Course::onlyTrashed()
            ->with('category:id,name')
            ->orderByDesc('deleted_at')
            ->limit(200)
            ->get();

        // Why each one survived deletion — the same counts `DeleteCourseAction`
        // weighed when it chose to soft-delete. Without them the screen says a
        // course was removed and gives no reason, and the reason is the entire
        // justification for the row still existing.
        $dependents = app(DeleteCourseAction::class);

        return [
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'category' => $course->category?->name,
                'workflow_status' => $course->workflow_status?->value,
                'deleted_at' => $course->deleted_at?->toDateTimeString(),
                'holds' => $dependents->dependentCounts($course),
                'enrolled' => (int) DB::table('course_enrollments')
                    ->where('course_id', $course->id)
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->count(),
            ])->values()->all(),
        ];
    }
}
