<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Academics\Actions\StudentIsOnClassRosterAction;
use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;

class AuthorizeAssessmentAccessAction
{
    /**
     * @return array{assessment_id: int, course_id: int|null, classroom_id: int|null, enrollment_id: int|null, student_id: int, academic_year_id: int|null}
     */
    public function execute(int $assessmentId, int $userId): array
    {
        $assessment = Assessment::query()->findOrFail($assessmentId);
        abort_unless($assessment->status === AssessmentStatus::Published, 403, 'Assessment is not published.');

        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        abort_unless($student !== null, 403, 'A student profile is required.');

        if ($assessment->classroom_id) {
            abort_unless(
                app(StudentIsOnClassRosterAction::class)->execute((int) $student['id'], (int) $assessment->classroom_id),
                403,
                'Class roster membership is required.',
            );

            return [
                'assessment_id' => $assessment->id,
                'course_id' => null,
                'classroom_id' => (int) $assessment->classroom_id,
                'enrollment_id' => null,
                'student_id' => (int) $student['id'],
                'academic_year_id' => $assessment->academic_year_id ? (int) $assessment->academic_year_id : null,
            ];
        }

        abort_unless($assessment->course_id, 403, 'Assessment is not attached to a course or class.');

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $assessment->course_id)
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->first();
        abort_unless($enrollment !== null, 403, 'Enrollment is required.');

        return [
            'assessment_id' => $assessment->id,
            'course_id' => $assessment->course_id,
            'classroom_id' => null,
            'enrollment_id' => $enrollment->id,
            'student_id' => (int) $student['id'],
            'academic_year_id' => null,
        ];
    }
}
