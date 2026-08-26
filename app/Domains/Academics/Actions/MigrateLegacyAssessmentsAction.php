<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Legacy\Models\Assignment;
use App\Domains\Academics\Legacy\Models\AssignmentSubmission;
use App\Domains\Academics\Legacy\Models\Quiz;
use App\Domains\Academics\Legacy\Models\QuizAttempt;
use App\Domains\Academics\Legacy\Models\QuizQuestion;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\BuildAssessmentSnapshotsAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Progress\Actions\ImportAssessmentAttemptAction;
use Illuminate\Support\Facades\DB;

class MigrateLegacyAssessmentsAction
{
    /**
     * @return array{
     *     quizzes: array{source: int, migrated: int, remaining: list<int>},
     *     quiz_questions: array{source: int, migrated: int, remaining: list<int>},
     *     quiz_attempts: array{source: int, migrated: int, remaining: list<int>},
     *     assignments: array{source: int, migrated: int, remaining: list<int>},
     *     assignment_submissions: array{source: int, migrated: int, remaining: list<int>}
     * }
     */
    public function execute(): array
    {
        $this->migrateQuizzes();
        $this->migrateAssignments();

        return $this->verify();
    }

    /**
     * @return array{
     *     quizzes: array{source: int, migrated: int, remaining: list<int>},
     *     quiz_questions: array{source: int, migrated: int, remaining: list<int>},
     *     quiz_attempts: array{source: int, migrated: int, remaining: list<int>},
     *     assignments: array{source: int, migrated: int, remaining: list<int>},
     *     assignment_submissions: array{source: int, migrated: int, remaining: list<int>}
     * }
     */
    public function verify(): array
    {
        return [
            'quizzes' => $this->bucket(
                Quiz::query()->orderBy('id')->pluck('id')->all(),
                DB::table('assessments')->whereNotNull('legacy_quiz_id')->pluck('legacy_quiz_id')->all(),
            ),
            'quiz_questions' => $this->bucket(
                QuizQuestion::query()->orderBy('id')->pluck('id')->all(),
                DB::table('questions')->whereNotNull('legacy_quiz_question_id')->pluck('legacy_quiz_question_id')->all(),
            ),
            'quiz_attempts' => $this->bucket(
                QuizAttempt::query()->orderBy('id')->pluck('id')->all(),
                DB::table('assessment_attempts')->whereNotNull('legacy_quiz_attempt_id')->pluck('legacy_quiz_attempt_id')->all(),
            ),
            'assignments' => $this->bucket(
                Assignment::query()->orderBy('id')->pluck('id')->all(),
                DB::table('assessments')->whereNotNull('legacy_assignment_id')->pluck('legacy_assignment_id')->all(),
            ),
            'assignment_submissions' => $this->bucket(
                AssignmentSubmission::query()->orderBy('id')->pluck('id')->all(),
                DB::table('assessment_attempts')->whereNotNull('legacy_assignment_submission_id')->pluck('legacy_assignment_submission_id')->all(),
            ),
        ];
    }

    public function isClean(array $report): bool
    {
        foreach ($report as $row) {
            if ($row['remaining'] !== []) {
                return false;
            }
        }

        return true;
    }

    private function migrateQuizzes(): void
    {
        foreach (Quiz::query()->orderBy('id')->get() as $quiz) {
            if (! $quiz->classroom_id) {
                continue;
            }
            $class = ClassRoom::query()->find($quiz->classroom_id);
            if ($class === null) {
                continue;
            }

            $questions = $quiz->questions()->orderBy('order')->get();
            $teacherMarked = $questions->contains(fn (QuizQuestion $question) => $question->type === 'essay');

            $assessment = app(SaveAssessmentAction::class)->execute([
                'classroom_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'assessment_type' => 'lesson_quiz',
                'status' => $quiz->status === 'published' ? 'published' : 'draft',
                'time_limit_minutes' => $quiz->time_limit_min,
                'passing_score' => $quiz->passing_score !== null ? (int) round((float) $quiz->passing_score) : null,
                'retake_limit' => $quiz->max_attempts,
                'randomize_questions' => (bool) $quiz->shuffle_questions,
                'show_results' => (bool) $quiz->show_results,
                'show_correct_answers' => (bool) $quiz->show_results,
                'requires_teacher_marking' => $teacherMarked,
                'created_by' => $this->teacherUserId($quiz->teacher_id),
                'legacy_quiz_id' => $quiz->id,
                'settings' => [
                    'legacy_status' => $quiz->status,
                    'starts_at' => optional($quiz->starts_at)?->toIso8601String(),
                    'ends_at' => optional($quiz->ends_at)?->toIso8601String(),
                    'school_subject_id' => $quiz->subject_id,
                    'legacy_teacher_id' => $quiz->teacher_id,
                ],
            ]);

            $questionMap = [];
            foreach ($questions as $index => $legacyQuestion) {
                $payload = app(MapLegacyQuizQuestionAction::class)->execute($legacyQuestion);
                $payload['created_by'] = $this->teacherUserId($quiz->teacher_id);
                $payload['settings'] = array_merge($payload['settings'] ?? [], [
                    'school_subject_id' => $quiz->subject_id,
                ]);
                $question = app(SaveQuestionAction::class)->execute($payload);
                app(AttachAssessmentQuestionAction::class)->execute([
                    'assessment_id' => $assessment->id,
                    'question_id' => $question->id,
                    'position' => (int) ($legacyQuestion->order ?: ($index + 1)),
                    'points_override' => max(1, (int) $legacyQuestion->points),
                ]);
                $questionMap[(int) $legacyQuestion->id] = $question->id;
            }

            foreach ($quiz->attempts()->orderBy('id')->get() as $attempt) {
                $this->importQuizAttempt($assessment->id, $class, $attempt, $questionMap);
            }
        }
    }

    private function migrateAssignments(): void
    {
        foreach (Assignment::query()->orderBy('id')->get() as $assignment) {
            $class = ClassRoom::query()->find($assignment->class_id);
            if ($class === null) {
                continue;
            }

            $assessment = app(SaveAssessmentAction::class)->execute([
                'classroom_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'assessment_type' => 'assignment',
                'status' => $assignment->status === 'published' && $assignment->is_active ? 'published' : 'draft',
                'max_score' => (int) $assignment->max_marks,
                'requires_teacher_marking' => true,
                'created_by' => $this->teacherUserId($assignment->teacher_id),
                'legacy_assignment_id' => $assignment->id,
                'settings' => [
                    'legacy_status' => $assignment->status,
                    'legacy_type' => $assignment->type,
                    'title_arabic' => $assignment->title_arabic,
                    'title_dhivehi' => $assignment->title_dhivehi,
                    'instructions' => $assignment->instructions,
                    'instructions_arabic' => $assignment->instructions_arabic,
                    'instructions_dhivehi' => $assignment->instructions_dhivehi,
                    'due_date' => optional($assignment->due_date)?->toDateString(),
                    'due_time' => $assignment->due_time instanceof \DateTimeInterface
                        ? $assignment->due_time->format('H:i')
                        : $assignment->due_time,
                    'allow_late_submission' => (bool) $assignment->allow_late_submission,
                    'late_penalty_percentage' => $assignment->late_penalty_percentage,
                    'school_subject_id' => $assignment->subject_id,
                    'legacy_teacher_id' => $assignment->teacher_id,
                    'attachments' => $assignment->attachments,
                ],
            ]);

            $question = app(SaveQuestionAction::class)->execute([
                'question_type' => 'file_submission',
                'question_text' => (string) ($assignment->instructions ?: $assignment->description ?: $assignment->title),
                'legacy_assignment_id' => $assignment->id,
                'created_by' => $this->teacherUserId($assignment->teacher_id),
                'settings' => [
                    'school_subject_id' => $assignment->subject_id,
                    'legacy_assignment_id' => $assignment->id,
                ],
            ]);
            app(AttachAssessmentQuestionAction::class)->execute([
                'assessment_id' => $assessment->id,
                'question_id' => $question->id,
                'points_override' => max(1, (int) $assignment->max_marks),
            ]);

            foreach ($assignment->submissions()->orderBy('id')->get() as $submission) {
                $this->importAssignmentSubmission($assessment->id, $class, $question->id, $assignment, $submission);
            }
        }
    }

    /**
     * @param  array<int, int>  $questionMap
     */
    private function importQuizAttempt(int $assessmentId, ClassRoom $class, QuizAttempt $attempt, array $questionMap): void
    {
        $snapshots = app(BuildAssessmentSnapshotsAction::class)->execute($assessmentId, false);
        $status = match ($attempt->status) {
            'in_progress' => 'in_progress',
            'submitted' => 'submitted',
            default => 'scored',
        };

        app(ImportAssessmentAttemptAction::class)->execute([
            'assessment_id' => $assessmentId,
            'enrollment_id' => null,
            'student_id' => $attempt->student_id,
            'course_id' => null,
            'classroom_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'attempt_number' => max(1, (int) $attempt->attempt_number),
            'status' => $status,
            'answers' => $this->mapQuizAnswers($attempt->answers, $questionMap),
            'snapshots' => $snapshots,
            'score' => $attempt->points_earned !== null
                ? (int) $attempt->points_earned
                : ($attempt->score !== null ? (int) round((float) $attempt->score) : null),
            'max_score' => $attempt->total_points !== null ? (int) $attempt->total_points : null,
            'started_at' => $attempt->started_at,
            'submitted_at' => $attempt->finished_at,
            'last_saved_at' => $attempt->finished_at ?? $attempt->started_at,
            'feedback' => $attempt->feedback,
            'legacy_quiz_attempt_id' => $attempt->id,
        ]);
    }

    private function importAssignmentSubmission(
        int $assessmentId,
        ClassRoom $class,
        int $questionId,
        Assignment $assignment,
        AssignmentSubmission $submission,
    ): void {
        $snapshots = app(BuildAssessmentSnapshotsAction::class)->execute($assessmentId, false);
        $status = $submission->status === 'submitted' ? 'submitted' : 'scored';

        app(ImportAssessmentAttemptAction::class)->execute([
            'assessment_id' => $assessmentId,
            'enrollment_id' => null,
            'student_id' => $submission->student_id,
            'course_id' => null,
            'classroom_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'attempt_number' => 1,
            'status' => $status,
            'answers' => [
                (string) $questionId => [
                    'text' => (string) ($submission->content ?? ''),
                    'attachments' => $submission->attachments ?? [],
                    'is_late' => (bool) $submission->is_late,
                ],
            ],
            'snapshots' => $snapshots,
            'score' => $submission->marks_obtained,
            'max_score' => (int) $assignment->max_marks,
            'started_at' => $submission->submitted_at,
            'submitted_at' => $submission->submitted_at,
            'last_saved_at' => $submission->submitted_at,
            'feedback' => $submission->teacher_feedback,
            'reviewed_by' => $submission->graded_by,
            'reviewed_at' => $submission->graded_at,
            'legacy_assignment_submission_id' => $submission->id,
        ]);
    }

    /**
     * @param  array<int, int>  $questionMap
     * @return array<string, mixed>
     */
    private function mapQuizAnswers(mixed $answers, array $questionMap): array
    {
        if (! is_array($answers)) {
            return [];
        }

        $mapped = [];
        foreach ($answers as $key => $value) {
            $legacyId = is_numeric($key) ? (int) $key : 0;
            $engineId = $questionMap[$legacyId] ?? null;
            if ($engineId === null) {
                continue;
            }
            if (is_array($value) && (isset($value['selected_ids']) || isset($value['text']) || isset($value['order']))) {
                $mapped[(string) $engineId] = $value;

                continue;
            }
            if (is_array($value)) {
                $mapped[(string) $engineId] = ['selected_ids' => array_map('strval', array_values($value))];

                continue;
            }
            $mapped[(string) $engineId] = ['text' => (string) $value, 'selected_ids' => [(string) $value]];
        }

        return $mapped;
    }

    /**
     * @param  list<int>  $source
     * @param  list<int|string>  $migrated
     * @return array{source: int, migrated: int, remaining: list<int>}
     */
    private function bucket(array $source, array $migrated): array
    {
        $mapped = array_map('intval', $migrated);
        $remaining = array_values(array_diff($source, $mapped));

        return [
            'source' => count($source),
            'migrated' => count($source) - count($remaining),
            'remaining' => $remaining,
        ];
    }

    private function teacherUserId(?int $teacherId): ?int
    {
        if (! $teacherId) {
            return null;
        }

        $userId = DB::table('teachers')->where('id', $teacherId)->value('user_id');

        return $userId ? (int) $userId : null;
    }
}
