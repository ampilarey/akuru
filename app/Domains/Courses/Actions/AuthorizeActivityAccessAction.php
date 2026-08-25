<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;

class AuthorizeActivityAccessAction
{
    /**
     * @return array{activity_id: int, course_id: int, enrollment_id: int, student_id: int, academic_year_id: int|null}
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
            'academic_year_id' => null,
        ];
    }
}
