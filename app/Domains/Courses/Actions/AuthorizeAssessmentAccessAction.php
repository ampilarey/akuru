<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;

class AuthorizeAssessmentAccessAction
{
    /**
     * @return array{assessment_id: int, course_id: int, enrollment_id: int, student_id: int, academic_year_id: int|null}
     */
    public function execute(int $assessmentId, int $userId): array
    {
        $assessment = Assessment::query()->findOrFail($assessmentId);
        abort_unless($assessment->status === AssessmentStatus::Published, 403, 'Assessment is not published.');

        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        abort_unless($student !== null, 403, 'A student profile is required.');

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $assessment->course_id)
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->first();
        abort_unless($enrollment !== null, 403, 'Enrollment is required.');

        return [
            'assessment_id' => $assessment->id,
            'course_id' => $assessment->course_id,
            'enrollment_id' => $enrollment->id,
            'student_id' => (int) $student['id'],
            'academic_year_id' => null,
        ];
    }
}
