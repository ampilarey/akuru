<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Enums\AssessmentType;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;
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
            // An assessment attached to another course's module or lesson is
            // not a validation nicety: §19 hangs Module ID and Lesson ID off
            // the assessment, and every reader assumes they are inside
            // `course_id`. Nothing checked it.
            'course_module_id' => $this->moduleInCourse($data['course_module_id'] ?? null, $courseId),
            'lesson_id' => $this->lessonInCourse($data['lesson_id'] ?? null, $courseId),
            'title' => $title,
            'description' => $data['description'] ?? null,
            // SPEC §19's eleven types, as an enum rather than whatever string
            // arrived. The vocabulary lived as a hardcoded array in a
            // controller and nothing validated against it, so any string was
            // storable — and `assessment_type` is what §19's reporting and the
            // §34–§37 dashboards group by.
            'assessment_type' => $this->assessmentType($data['assessment_type'] ?? null, $assessment),
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

    private function assessmentType(mixed $given, ?Assessment $assessment): AssessmentType
    {
        if ($given === null || $given === '') {
            return $assessment?->assessment_type instanceof AssessmentType
                ? $assessment->assessment_type
                : AssessmentType::LessonQuiz;
        }

        $type = AssessmentType::tryFrom((string) $given);
        if ($type === null) {
            throw ValidationException::withMessages([
                'assessment_type' => 'That is not one of the assessment types SPEC §19 defines.',
            ]);
        }

        return $type;
    }

    /**
     * `$courseId` is nullable because an assessment attaches to a **class or a
     * course, not both** — a classroom assessment has no course at all. With
     * no course there is nothing for a module to belong to, so there is
     * nothing to check: the ownership rule is about keeping a module inside
     * its own course, not about inventing one.
     */
    private function moduleInCourse(mixed $moduleId, ?int $courseId): ?int
    {
        $id = $this->nullableId($moduleId);
        if ($id === null || $courseId === null) {
            return $id;
        }

        $belongs = CourseModule::query()->where('id', $id)->where('course_id', $courseId)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([
                'course_module_id' => 'That module belongs to a different course.',
            ]);
        }

        return $id;
    }

    private function lessonInCourse(mixed $lessonId, ?int $courseId): ?int
    {
        $id = $this->nullableId($lessonId);
        if ($id === null || $courseId === null) {
            return $id;
        }

        $belongs = Lesson::query()->where('id', $id)->where('course_id', $courseId)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([
                'lesson_id' => 'That lesson belongs to a different course.',
            ]);
        }

        return $id;
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
