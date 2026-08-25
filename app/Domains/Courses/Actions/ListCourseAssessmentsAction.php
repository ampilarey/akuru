<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\AssessmentQuestion;
use App\Domains\Courses\Models\Course;
use Illuminate\Support\Collection;

class ListCourseAssessmentsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Course $course): Collection
    {
        return Assessment::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Assessment $assessment): array => $this->serialize($assessment));
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Assessment $assessment, bool $includeQuestionKeys = true): array
    {
        $questions = AssessmentQuestion::query()
            ->where('assessment_id', $assessment->id)
            ->orderBy('position')
            ->get()
            ->map(function (AssessmentQuestion $row) use ($includeQuestionKeys): array {
                $question = app(ListQuestionsAction::class)->serialize($row->question()->firstOrFail());
                if (! $includeQuestionKeys) {
                    unset($question['correct_answer'], $question['acceptable_answers'], $question['explanation']);
                }

                return [
                    'question_id' => $row->question_id,
                    'position' => $row->position,
                    'points_override' => $row->points_override,
                    'is_required' => (bool) $row->is_required,
                    'question' => $question,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $assessment->id,
            'course_id' => $assessment->course_id,
            'course_module_id' => $assessment->course_module_id,
            'lesson_id' => $assessment->lesson_id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'assessment_type' => $assessment->assessment_type,
            'status' => $assessment->status->value,
            'time_limit_minutes' => $assessment->time_limit_minutes,
            'passing_score' => $assessment->passing_score,
            'max_score' => (int) $assessment->max_score,
            'retake_limit' => $assessment->retake_limit,
            'randomize_questions' => (bool) $assessment->randomize_questions,
            'show_results' => (bool) $assessment->show_results,
            'show_correct_answers' => (bool) $assessment->show_correct_answers,
            'requires_teacher_marking' => (bool) $assessment->requires_teacher_marking,
            'questions' => $questions,
        ];
    }
}
