<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\IssuedCertificate;

/**
 * SPEC §24 lists "Certificate eligibility status" among what the Course
 * Learning Page must show.
 *
 * The engine for it was complete — `CheckCertificateEligibilityAction` weighs
 * minimum progress, payment, teacher approval, minimum attendance and minimum
 * score, with course-level rules overridden at offering level — and it had
 * exactly **one** caller: `IssueCertificateAction`. So eligibility was computed
 * only at the moment an admin issued the certificate. A student working toward
 * one could not see whether they were on track, or what was still missing.
 *
 * Which is the part that matters: the reasons. "Not yet" is discouraging;
 * "attendance is below the minimum" is something a student can act on.
 */
class ResolveCourseCertificateStatusAction
{
    /**
     * @return array<string, mixed>|null null when the course issues no certificate at all
     */
    public function execute(int $courseId, ?CourseEnrollment $enrollment, ?int $studentId): ?array
    {
        $template = CertificateTemplate::query()
            ->where('active', true)
            ->where('course_id', $courseId)
            ->orderBy('id')
            ->first();

        if ($template === null || $studentId === null) {
            return null;
        }

        // Already earned: say so plainly rather than re-running the rules, which
        // could report a student as "not eligible" for a certificate they are
        // holding — if, say, the thresholds were raised afterwards.
        $issued = IssuedCertificate::query()
            ->where('certificate_template_id', $template->id)
            ->where('student_id', $studentId)
            // A revoked certificate is not one the student holds.
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->first();

        if ($issued !== null) {
            return [
                'template' => $template->name,
                'issued' => true,
                'eligible' => true,
                'reasons' => [],
                'certificate_number' => $issued->certificate_number,
            ];
        }

        $eligibility = app(CheckCertificateEligibilityAction::class)->execute(
            $template,
            $studentId,
            $courseId,
            $enrollment?->course_offering_id ? (int) $enrollment->course_offering_id : null,
            // `teacher_approved` is deliberately not asserted here. The action
            // treats absent as not approved, so a course that requires sign-off
            // shows "Teacher approval is required" as an outstanding reason —
            // which is true, and is exactly what the student needs to know.
            [],
        );

        return [
            'template' => $template->name,
            'issued' => false,
            'eligible' => $eligibility['eligible'],
            'reasons' => $eligibility['reasons'],
            'certificate_number' => null,
        ];
    }
}
