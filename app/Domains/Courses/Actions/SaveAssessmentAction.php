<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use Illuminate\Validation\ValidationException;

class SaveAssessmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Assessment $assessment = null): Assessment
    {
        $course = Course::query()->findOrFail((int) $data['course_id']);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Assessment title is required.']);
        }

        $status = AssessmentStatus::tryFrom((string) ($data['status'] ?? AssessmentStatus::Draft->value))
            ?? AssessmentStatus::Draft;

        $payload = [
            'course_id' => $course->id,
            'course_module_id' => $data['course_module_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'assessment_type' => (string) ($data['assessment_type'] ?? 'lesson_quiz'),
            'status' => $status,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'passing_score' => $data['passing_score'] ?? null,
            'max_score' => (int) ($data['max_score'] ?? 0),
            'retake_limit' => $data['retake_limit'] ?? null,
            'randomize_questions' => (bool) ($data['randomize_questions'] ?? false),
            'show_results' => (bool) ($data['show_results'] ?? true),
            'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? false),
            'requires_teacher_marking' => (bool) ($data['requires_teacher_marking'] ?? false),
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [
                'lock_next_lesson' => (bool) (($data['settings']['lock_next_lesson'] ?? false)),
            ],
            'created_by' => $data['created_by'] ?? $assessment?->created_by,
        ];

        if ($assessment === null) {
            return Assessment::query()->create($payload);
        }

        $assessment->fill($payload);
        $assessment->save();

        return $assessment->fresh();
    }
}
