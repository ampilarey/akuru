<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;

class AuthorizeActivityAccessAction
{
    /**
     * @return array{activity_id: int, course_id: int, enrollment_id: int, student_id: int, academic_year_id: int|null
    private function currentAcademicYearId(): ?int
    {
        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        return isset($year['id']) ? (int) $year['id'] : null;
    }
}
     */
    public function execute(int $activityId, int $userId): array
    {
        $activity = Activity::query()->findOrFail($activityId);
        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        abort_unless($student !== null, 403, 'A student profile is required.');

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $activity->course_id)
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->first();
        abort_unless($enrollment !== null, 403, 'Enrollment is required.');

        return [
            'activity_id' => $activity->id,
            'course_id' => $activity->course_id,
            'enrollment_id' => $enrollment->id,
            'student_id' => (int) $student['id'],
            // Rule 10: an attempt is something that happens in time, and
            // `activity_attempts` carries the column for it. This used to be a
            // hardcoded `null`, so **every** attempt ever written was
            // yearless — the column existed to satisfy the rule and nothing
            // ever filled it.
            //
            // Today's year, because that is what the column means for an
            // attempt: when the student did it. Resolved through Academics'
            // own action rather than by reading the table here (rule 3), so
            // "which year is this date in" has one answer.
            'academic_year_id' => $this->currentAcademicYearId(),
        ];
    }

    private function currentAcademicYearId(): ?int
    {
        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        return isset($year['id']) ? (int) $year['id'] : null;
    }
}
