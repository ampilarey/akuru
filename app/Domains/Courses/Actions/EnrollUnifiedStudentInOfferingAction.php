<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\EnsureLegacyStudentForUnifiedAction;

class EnrollUnifiedStudentInOfferingAction
{
    public function execute(int $unifiedStudentId, int $courseId, int $offeringId, ?int $createdBy = null): CourseEnrollment
    {
        $existing = CourseEnrollment::query()
            ->where('course_id', $courseId)
            ->where('unified_student_id', $unifiedStudentId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $legacyId = app(EnsureLegacyStudentForUnifiedAction::class)->execute($unifiedStudentId);

        return CourseEnrollment::query()->create([
            'student_id' => $legacyId,
            'unified_student_id' => $unifiedStudentId,
            'course_id' => $courseId,
            'course_offering_id' => $offeringId,
            'status' => 'active',
            'enrollment_type' => 'free',
            'progress_percentage' => 0,
            'enrolled_at' => now(),
            'created_by_user_id' => $createdBy,
            'payment_status' => 'not_required',
        ]);
    }
}
