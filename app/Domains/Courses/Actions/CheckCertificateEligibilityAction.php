<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\GetOfferingAttendancePercentAction;
use App\Domains\Offerings\Actions\GetOfferingCertificateRulesAction;
use App\Domains\Progress\Actions\ListAssessmentScoresAction;

class CheckCertificateEligibilityAction
{
    /**
     * @param  array{teacher_approved?: bool, assessment_id?: int}  $context
     * @return array{eligible: bool, reasons: list<string>}
     */
    public function execute(
        CertificateTemplate $template,
        int $studentId,
        ?int $courseId = null,
        ?int $offeringId = null,
        array $context = [],
    ): array {
        $rules = is_array($template->rules) ? $template->rules : [];
        if ($offeringId) {
            $override = app(GetOfferingCertificateRulesAction::class)->execute($offeringId);
            foreach ($override as $key => $value) {
                if ($value !== null && $value !== '') {
                    $rules[$key] = $value;
                }
            }
        }

        if ($template->kind === CertificateKind::Manual) {
            return ['eligible' => true, 'reasons' => []];
        }

        $courseId = $courseId ?? $template->course_id;
        $enrollment = CourseEnrollment::query()
            ->where('unified_student_id', $studentId)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($offeringId, fn ($query) => $query->where('course_offering_id', $offeringId))
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderByDesc('id')
            ->first();

        if ($enrollment === null) {
            return ['eligible' => false, 'reasons' => ['No matching enrollment.']];
        }

        $reasons = [];
        $minProgress = $this->nullableInt($rules['min_progress_percent'] ?? null);
        if ($minProgress !== null && (int) $enrollment->progress_percentage < $minProgress) {
            $reasons[] = 'Progress is below the minimum.';
        }

        if (($rules['require_payment'] ?? false) && ! in_array((string) $enrollment->payment_status, ['paid', 'confirmed', 'not_required'], true)) {
            $reasons[] = 'Payment is not complete.';
        }

        if (($rules['require_teacher_approval'] ?? false) && empty($context['teacher_approved'])) {
            $reasons[] = 'Teacher approval is required.';
        }

        $minAttendance = $this->nullableInt($rules['min_attendance_percent'] ?? null);
        if ($offeringId && $minAttendance !== null) {
            $percent = app(GetOfferingAttendancePercentAction::class)->execute($offeringId, $studentId);
            if ($percent === null || $percent < $minAttendance) {
                $reasons[] = 'Attendance is below the minimum.';
            }
        }

        $needAssessment = ($rules['require_final_assessment'] ?? false)
            || $this->nullableInt($rules['min_score'] ?? null) !== null
            || $template->kind === CertificateKind::Assessment;
        if ($needAssessment) {
            $assessmentId = $this->nullableInt($context['assessment_id'] ?? $rules['assessment_id'] ?? null);
            $ids = $assessmentId ? [$assessmentId] : $this->assessmentIds((int) $enrollment->course_id);
            $scores = app(ListAssessmentScoresAction::class)->execute($ids, [$studentId]);
            $best = null;
            foreach ($scores as $byStudent) {
                $score = $byStudent[$studentId]['score'] ?? null;
                if ($score !== null) {
                    $best = $best === null ? (float) $score : max($best, (float) $score);
                }
            }
            if (($rules['require_final_assessment'] ?? false) && $best === null) {
                $reasons[] = 'Required assessment has no score.';
            }
            $minScore = $this->nullableInt($rules['min_score'] ?? null);
            if ($minScore !== null && ($best === null || $best < $minScore)) {
                $reasons[] = 'Assessment score is below the minimum.';
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    /**
     * @return list<int>
     */
    private function assessmentIds(int $courseId): array
    {
        return Assessment::query()
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
