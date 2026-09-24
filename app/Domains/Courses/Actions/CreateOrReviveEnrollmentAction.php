<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * One enrollment per student, per course, per term — created, or brought back.
 *
 * The database has said so since the table was made:
 *
 *     UNIQUE KEY (student_id, course_id, term_key)   -- term_key = IFNULL(term_id, 0)
 *
 * and, since Deploy 3 slice 1, on the unified student every read uses:
 *
 *     UNIQUE KEY (unified_student_id, course_id, term_key)
 *
 * Both enrollment Actions decided who was already enrolled by a *different*
 * rule — "is there a row here whose status is not rejected or cancelled" — and
 * then inserted when the answer was no. The generosity is deliberate and right:
 * somebody who withdrew, or whose payment was refunded, should be able to come
 * back. But the key does not share the opinion, and neither does the soft-delete
 * column, so the insert that followed that decision hit a duplicate key and the
 * learner got a 500 (STATUS §5ei).
 *
 * The two halves were each plausible alone. The guard reads as generosity; the
 * key reads as hygiene; nothing brought them together until somebody was
 * refunded and tried to enrol again.
 *
 * So the key is the single source of truth about identity (rule 11) and lives
 * here, once, rather than in each caller:
 *
 *   - no row on that key → create;
 *   - a row that is rejected, cancelled or soft-deleted → **revive it** with
 *     the new enrollment's attributes;
 *   - anything else → hand back what is already there, untouched.
 *
 * Reviving rather than inserting keeps the enrollment history in one row, which
 * is what every report, certificate check and access window already assumes
 * when it looks a student's enrollment up by course.
 */
class CreateOrReviveEnrollmentAction
{
    /**
     * States that mean "this enrollment has ended" — the same list both
     * enrollment Actions skip when deciding whether somebody is enrolled.
     */
    private const ENDED = ['rejected', 'cancelled'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): CourseEnrollment
    {
        $existing = CourseEnrollment::query()
            // Soft-deleted rows still occupy the unique key, so a lookup that
            // cannot see them is a lookup that crashes on the insert instead.
            ->withTrashed()
            // The key is on the unified student since Deploy 3 slice 1
            // (`course_enrollments_unified_student_course_term_unique`).
            ->where('unified_student_id', $attributes['unified_student_id'])
            ->where('course_id', $attributes['course_id'])
            // The generated column's own definition, matching how
            // `EnrollmentService` already asks this question.
            ->whereRaw('IFNULL(term_id, 0) = ?', [$attributes['term_id'] ?? 0])
            ->first();

        if ($existing === null) {
            return CourseEnrollment::query()->create($attributes);
        }

        if ($existing->deleted_at === null && ! in_array($existing->status, self::ENDED, true)) {
            return $existing;
        }

        // `student_lesson_progress` is keyed by student and lesson, not by
        // enrollment, so it survives a cancellation untouched. Zeroing the
        // rollup here would have the catalog report 0% over lessons the student
        // can see are finished, so a revived enrollment keeps what it earned.
        $attributes['progress_percentage'] = max(
            (int) ($attributes['progress_percentage'] ?? 0),
            (int) $existing->progress_percentage,
        );

        $existing->fill($attributes);
        $existing->deleted_at = null;
        $existing->save();

        return $existing->refresh();
    }
}
