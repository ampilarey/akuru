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

            // SPEC §27 "Reach minimum score". `min_score` is a percentage
            // everywhere it is written — the request validates it `max:100`,
            // the builder's input caps at 100, and it sits between
            // `min_progress_percent` and `min_attendance_percent`. It was
            // compared against the **raw mark**, so the threshold meant
            // whatever the assessment happened to be marked out of:
            //
            //   - 10/10 on a ten-mark quiz is 100%, and failed `min_score: 50`.
            //   - 60/200 on an exam is 30%, and passed it.
            //
            // An admin could not even express a raw threshold above 100 marks,
            // because the field refuses one. Percent is the only reading the
            // rest of the system supports, so percent is what it compares.
            $best = null;
            $awaitingMarking = false;
            foreach ($scores as $byStudent) {
                $row = $byStudent[$studentId] ?? null;
                if ($row === null) {
                    continue;
                }
                // A submitted attempt carries a provisional auto-score waiting
                // for a teacher (SPEC §19). Treating it as a mark meant a
                // certificate could be granted on a number no human had agreed
                // to, and that a later marking could contradict.
                if (! ($row['is_final'] ?? false)) {
                    $awaitingMarking = true;

                    continue;
                }
                $percent = $this->percentage($row['score'] ?? null, $row['max_score'] ?? null);
                if ($percent !== null) {
                    $best = $best === null ? $percent : max($best, $percent);
                }
            }

            if (($rules['require_final_assessment'] ?? false) && $best === null) {
                $reasons[] = $awaitingMarking
                    ? 'Required assessment is awaiting teacher marking.'
                    : 'Required assessment has no score.';
            }
            $minScore = $this->nullableInt($rules['min_score'] ?? null);
            if ($minScore !== null && ($best === null || $best < $minScore)) {
                $reasons[] = $best === null && $awaitingMarking
                    ? 'Required assessment is awaiting teacher marking.'
                    : 'Assessment score is below the minimum.';
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    /**
     * A score as a percentage of what the attempt was marked out of.
     *
     * An attempt with no `max_score` cannot be turned into a percentage, so it
     * does not count toward the threshold — silently treating it as the raw
     * number is the bug this replaces.
     */
    private function percentage(mixed $score, mixed $maxScore): ?float
    {
        if ($score === null || $maxScore === null || (float) $maxScore <= 0.0) {
            return null;
        }

        return ((float) $score / (float) $maxScore) * 100.0;
    }

    /**
     * Every published assessment on the course — the fallback when the
     * template does not name one.
     *
     * This is a weak reading of "Pass final assessment": the best percentage
     * across any published assessment satisfies it, a practice quiz included.
     * `assessment_id` is the precise answer and is now settable in the builder;
     * before this slice it was validated and stored but had no control, so the
     * fallback was the only behaviour reachable.
     *
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
