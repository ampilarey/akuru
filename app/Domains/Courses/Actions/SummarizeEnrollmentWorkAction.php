<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Progress\Actions\ListAssessmentScoresAction;

/**
 * What is still owed, and what has been marked, for one student in one course.
 *
 * SPEC §24 lists twelve things the student dashboard must show. Five were
 * served. This Action supplies four of the missing seven — **pending lessons,
 * pending assessments, scores and teacher feedback** — and it lives apart from
 * `ListStudentDashboardAction` because the dashboard's job is to assemble a
 * page while this one's job is to answer a question about a course.
 *
 * ## Why the score is resolved here and not in Progress
 *
 * §19's `show_results` decides whether the student sees the mark at all, and
 * it is a *course engine* setting — `ResolveAssessmentSettingsAction` owns it.
 * `ListAssessmentScoresAction` deliberately does not apply it, because a
 * teacher report runs through the same Action and hiding scores there would
 * blank the report. So the hiding happens on this side of the boundary, where
 * the setting lives, exactly as `StartAssessmentAttemptAction::serialize()`
 * does it for the assessment page.
 *
 * Getting that wrong would quietly undo the §19 slice: a teacher who turned
 * marks off would still have had them shown, one screen further out.
 *
 * Feedback is **not** hidden with the mark. §19's switch is about the score,
 * and a teacher who wrote a comment meant the student to read it.
 */
class SummarizeEnrollmentWorkAction
{
    /**
     * @param  list<int>  $completedLessonIds
     * @return array{
     *     total_lessons: int,
     *     completed_lessons: int,
     *     pending_lessons: int,
     *     pending_assessments: int,
     *     assessments: list<array<string, mixed>>,
     *     feedback: list<array{title: string, feedback: string}>
     * }
     */
    public function execute(?int $courseId, ?int $studentId, array $completedLessonIds): array
    {
        $empty = [
            'total_lessons' => 0,
            'completed_lessons' => count($completedLessonIds),
            'pending_lessons' => 0,
            'pending_assessments' => 0,
            'assessments' => [],
            'feedback' => [],
        ];

        if ($courseId === null) {
            return $empty;
        }

        // A lesson without a published revision is not one a student can be
        // asked to do, so it must not be counted as owed either — the same
        // `whereNotNull('current_revision_id')` the learning page filters on.
        $totalLessons = Lesson::query()
            ->where('course_id', $courseId)
            ->whereNotNull('current_revision_id')
            ->count();

        $assessments = Assessment::query()
            ->where('course_id', $courseId)
            ->where('status', AssessmentStatus::Published)
            ->orderBy('id')
            ->get();

        $scores = $studentId !== null && $assessments->isNotEmpty()
            ? app(ListAssessmentScoresAction::class)->execute(
                $assessments->pluck('id')->map('intval')->all(),
                [$studentId],
            )
            : [];

        $rows = [];
        $feedback = [];
        $pending = 0;

        foreach ($assessments as $assessment) {
            $row = $scores[(int) $assessment->id][$studentId] ?? null;
            $settings = app(ResolveAssessmentSettingsAction::class)->execute((int) $assessment->id);
            $showResults = (bool) ($settings['show_results'] ?? true);

            // "Pending" means the student still owes work: never opened it, or
            // opened it and has not submitted. A submitted attempt waiting on a
            // teacher is not the student's to chase, so it is reported as
            // awaiting marking rather than counted as outstanding.
            $status = $row['status'] ?? 'not_started';
            if (in_array($status, ['not_started', 'in_progress'], true)) {
                $pending++;
            }

            $hideScore = ! $showResults && $status !== 'not_started';

            $rows[] = [
                'id' => (int) $assessment->id,
                'title' => (string) $assessment->title,
                'assessment_type' => $assessment->assessment_type instanceof \BackedEnum
                    ? $assessment->assessment_type->value
                    : $assessment->assessment_type,
                'status' => $status,
                'is_final' => (bool) ($row['is_final'] ?? false),
                'score' => $hideScore ? null : ($row['score'] ?? null),
                'max_score' => $hideScore ? null : ($row['max_score'] ?? null),
                'show_results' => ! $hideScore,
            ];

            $comment = trim((string) ($row['feedback'] ?? ''));
            if ($comment !== '') {
                $feedback[] = ['title' => (string) $assessment->title, 'feedback' => $comment];
            }
        }

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => count($completedLessonIds),
            'pending_lessons' => max(0, $totalLessons - count($completedLessonIds)),
            'pending_assessments' => $pending,
            'assessments' => $rows,
            'feedback' => $feedback,
        ];
    }
}
