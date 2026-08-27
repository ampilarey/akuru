<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\DefaultSelfLearningOfferingAction;
use App\Domains\Offerings\Actions\ReserveOfferingSeatAction;
use App\Domains\People\Actions\EnsureLegacyStudentForUnifiedAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollSelfLearningAction
{
    /**
     * Phase 4: the paid checkout passes $overrides to create the SAME
     * enrollment shape with pending/paid fields — one creator for both
     * paths, no duplicated seat/offering mechanics.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function execute(int $userId, int $courseId, ?int $offeringId = null, array $overrides = []): CourseEnrollment
    {
        $course = Course::query()->findOrFail($courseId);
        $status = $course->workflow_status instanceof CourseWorkflowStatus
            ? $course->workflow_status
            : CourseWorkflowStatus::tryFrom((string) $course->workflow_status);

        if ($status !== CourseWorkflowStatus::Published) {
            throw ValidationException::withMessages([
                'course_id' => 'Only published courses can be enrolled.',
            ]);
        }

        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($student === null) {
            throw ValidationException::withMessages([
                'student' => 'A student profile is required to enroll.',
            ]);
        }

        $existing = CourseEnrollment::query()
            ->where('course_id', $courseId)
            ->where('unified_student_id', $student['id'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $legacyId = $student['legacy_registration_student_id']
            ?? app(EnsureLegacyStudentForUnifiedAction::class)->execute($student['id']);

        return DB::transaction(function () use ($userId, $courseId, $offeringId, $student, $legacyId, $overrides): CourseEnrollment {
            $offering = $offeringId
                ? app(ReserveOfferingSeatAction::class)->execute($offeringId)
                : app(DefaultSelfLearningOfferingAction::class)->execute($courseId);
            if ($offering !== null && $offeringId === null && isset($offering['id'])) {
                $offering = app(ReserveOfferingSeatAction::class)->execute((int) $offering['id']);
            }
            if ($offering !== null && (int) $offering['course_id'] !== $courseId) {
                throw ValidationException::withMessages([
                    'course_offering_id' => 'Offering does not belong to this course.',
                ]);
            }

            return CourseEnrollment::query()->create(array_merge([
                'student_id' => $legacyId,
                'unified_student_id' => $student['id'],
                'course_id' => $courseId,
                'course_offering_id' => $offering['id'] ?? null,
                'status' => 'active',
                'enrollment_type' => 'free',
                'progress_percentage' => 0,
                'enrolled_at' => now(),
                'created_by_user_id' => $userId,
                'payment_status' => 'not_required',
            ], $overrides));
        });
    }
}
