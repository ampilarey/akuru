<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Courses\Actions\BuildAssessmentSnapshotsAction;
use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class StartAssessmentAttemptAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        int $assessmentId,
        ?int $enrollmentId,
        int $studentId,
        ?int $courseId,
        ?int $academicYearId = null,
        ?int $classroomId = null,
    ): array {
        $existing = $this->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->where('status', AssessmentAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        if ($existing !== null) {
            return $this->serialize($existing);
        }

        $settings = app(ResolveAssessmentSettingsAction::class)->execute($assessmentId);
        $this->assertRetakesAvailable($assessmentId, $enrollmentId, $settings['retake_limit'] ?? null, $studentId);

        $snapshots = app(BuildAssessmentSnapshotsAction::class)->execute(
            $assessmentId,
            (bool) $settings['randomize_questions'],
        );

        $attempt = AssessmentAttempt::query()->create([
            'assessment_id' => $assessmentId,
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'classroom_id' => $classroomId,
            'academic_year_id' => $academicYearId,
            'attempt_number' => $this->nextNumber($assessmentId, $enrollmentId, $studentId),
            'status' => AssessmentAttemptStatus::InProgress,
            'answers' => [],
            'snapshots' => $snapshots,
            'started_at' => now(),
            'last_saved_at' => now(),
        ]);

        return $this->serialize($attempt);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  bool  $asStudent  Apply SPEC §19's `show_results`, which hides the mark from the
     *                           person who sat the assessment. Off by default and passed only
     *                           from the student-facing paths — `ListScoredAttemptsAction` feeds
     *                           a *teacher* report through here without `includeKeys`, so hanging
     *                           this off that flag would have blanked scores for teachers.
     */
    public function serialize(AssessmentAttempt $attempt, bool $includeKeys = false, bool $asStudent = false): array
    {
        $snapshots = $attempt->snapshots ?? [];
        if (! $includeKeys) {
            $snapshots = array_map(function (array $snapshot): array {
                unset($snapshot['correct_answer'], $snapshot['acceptable_answers'], $snapshot['explanation']);

                return $snapshot;
            }, $snapshots);
        }

        // SPEC §19 "Show/hide correct answers" has a sibling the code never
        // read: `show_results`, which is whether the student sees the mark at
        // all. It was captured, saved, listed and resolved into settings, and
        // consumed nowhere — so a teacher who turned it off still showed the
        // score. It matters most where marking is not finished: a provisional
        // auto-total on an assessment awaiting a teacher reads as the grade.
        $hideScore = $asStudent && ! $this->showsResults($attempt);

        return [
            'id' => $attempt->id,
            'assessment_id' => $attempt->assessment_id,
            'enrollment_id' => $attempt->enrollment_id,
            'classroom_id' => $attempt->classroom_id,
            'student_id' => $attempt->student_id ? (int) $attempt->student_id : null,
            'course_id' => $attempt->course_id ? (int) $attempt->course_id : null,
            'academic_year_id' => $attempt->academic_year_id ? (int) $attempt->academic_year_id : null,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'answers' => $attempt->answers,
            'snapshots' => $snapshots,
            'score' => $hideScore ? null : $attempt->score,
            'max_score' => $hideScore ? null : $attempt->max_score,
            'show_results' => ! $hideScore,
            'started_at' => optional($attempt->started_at)?->toIso8601String(),
            'last_saved_at' => optional($attempt->last_saved_at)?->toIso8601String(),
            'submitted_at' => optional($attempt->submitted_at)?->toIso8601String(),
            // Feedback is deliberately NOT hidden: `show_results` is about the
            // mark. A teacher who wrote a comment meant the student to read it.
            'feedback' => $attempt->feedback,
            'item_scores' => $hideScore ? null : $attempt->item_scores,
            'reviewed_at' => optional($attempt->reviewed_at)?->toIso8601String(),
            // SPEC §31: the countdown is served, not inferred. A client that
            // computes remaining time from its own clock disagrees with the
            // server the moment the device clock is wrong — and the student
            // discovers it only when their submission is refused.
            ...$this->deadlineFields($attempt),
        ];
    }

    private function showsResults(AssessmentAttempt $attempt): bool
    {
        $settings = app(\App\Domains\Courses\Actions\ResolveAssessmentSettingsAction::class)
            ->execute((int) $attempt->assessment_id);

        return (bool) ($settings['show_results'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    private function deadlineFields(AssessmentAttempt $attempt): array
    {
        $settings = app(\App\Domains\Courses\Actions\ResolveAssessmentSettingsAction::class)
            ->execute((int) $attempt->assessment_id);

        $deadline = app(ResolveAssessmentDeadlineAction::class)->execute($attempt, $settings);

        return [
            'time_limit_minutes' => $settings['time_limit_minutes'] ?? null,
            'deadline_at' => $deadline['deadline']?->toIso8601String(),
            'seconds_remaining' => $deadline['seconds_remaining'],
            'expired' => $deadline['expired'],
        ];
    }

    public function assertRetakesAvailable(int $assessmentId, ?int $enrollmentId, mixed $retakeLimit, ?int $studentId = null): void
    {
        $submitted = $this->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->whereIn('status', [AssessmentAttemptStatus::Submitted, AssessmentAttemptStatus::Scored])
            ->count();

        if ($retakeLimit !== null && $submitted >= (int) $retakeLimit) {
            throw ValidationException::withMessages([
                'attempt' => ['Retake limit reached.'],
            ]);
        }
    }

    /**
     * @return Builder<AssessmentAttempt>
     */
    public function scopedQuery(int $assessmentId, ?int $enrollmentId, ?int $studentId = null): Builder
    {
        $query = AssessmentAttempt::query()->where('assessment_id', $assessmentId);

        if ($enrollmentId !== null) {
            return $query->where('enrollment_id', $enrollmentId);
        }

        return $query->where('student_id', (int) $studentId)->whereNull('enrollment_id');
    }

    private function nextNumber(int $assessmentId, ?int $enrollmentId, ?int $studentId): int
    {
        return ((int) $this->scopedQuery($assessmentId, $enrollmentId, $studentId)->max('attempt_number')) + 1;
    }
}
