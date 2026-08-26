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
        if ($assessment === null && ! empty($data['legacy_quiz_id'])) {
            $assessment = Assessment::query()->where('legacy_quiz_id', (int) $data['legacy_quiz_id'])->first();
        }
        if ($assessment === null && ! empty($data['legacy_assignment_id'])) {
            $assessment = Assessment::query()->where('legacy_assignment_id', (int) $data['legacy_assignment_id'])->first();
        }

        $courseId = $this->nullableId($data['course_id'] ?? $assessment?->course_id);
        $classroomId = $this->nullableId($data['classroom_id'] ?? $assessment?->classroom_id);

        if ($courseId !== null && $classroomId !== null) {
            throw ValidationException::withMessages([
                'course_id' => 'Attach an assessment to a course or a class, not both.',
            ]);
        }
        if ($courseId === null && $classroomId === null) {
            throw ValidationException::withMessages([
                'course_id' => 'Assessment must attach to a course or a class.',
            ]);
        }

        if ($courseId !== null) {
            Course::query()->findOrFail($courseId);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Assessment title is required.']);
        }

        $status = AssessmentStatus::tryFrom((string) ($data['status'] ?? AssessmentStatus::Draft->value))
            ?? AssessmentStatus::Draft;

        $payload = [
            'course_id' => $courseId,
            'classroom_id' => $classroomId,
            'academic_year_id' => $this->nullableId($data['academic_year_id'] ?? $assessment?->academic_year_id),
            'term_id' => $this->nullableId($data['term_id'] ?? $assessment?->term_id),
            'course_module_id' => $data['course_module_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'assessment_type' => (string) ($data['assessment_type'] ?? 'lesson_quiz'),
            'status' => $status,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'passing_score' => $data['passing_score'] ?? null,
            'max_score' => (int) ($data['max_score'] ?? $assessment?->max_score ?? 0),
            'retake_limit' => $data['retake_limit'] ?? null,
            'randomize_questions' => (bool) ($data['randomize_questions'] ?? false),
            'show_results' => (bool) ($data['show_results'] ?? true),
            'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? false),
            'requires_teacher_marking' => (bool) ($data['requires_teacher_marking'] ?? false),
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [
                'lock_next_lesson' => (bool) (($data['settings']['lock_next_lesson'] ?? false)),
            ],
            'created_by' => $data['created_by'] ?? $assessment?->created_by,
            'legacy_quiz_id' => $this->nullableId($data['legacy_quiz_id'] ?? $assessment?->legacy_quiz_id),
            'legacy_assignment_id' => $this->nullableId($data['legacy_assignment_id'] ?? $assessment?->legacy_assignment_id),
        ];

        if ($assessment === null) {
            return Assessment::query()->create($payload);
        }

        $assessment->fill($payload);
        $assessment->save();

        return $assessment->fresh();
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
