<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\Rubric;

/**
 * The rubrics screen of one course (Moodle parity slice M2, STATUS §5oi): its
 * rubrics with the items each marks, and the items a rubric can mark.
 */
class ListCourseRubricsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Course $course): array
    {
        $activities = Activity::query()->where('course_id', $course->id)->orderBy('id')->get(['id', 'title', 'pattern', 'rubric_id']);
        $assessments = Assessment::query()->where('course_id', $course->id)->whereNull('classroom_id')->orderBy('id')
            ->get(['id', 'title', 'assessment_type', 'requires_teacher_marking', 'rubric_id']);

        return [
            'course' => ['id' => (int) $course->id, 'title' => (string) $course->title],
            'rubrics' => Rubric::query()->where('course_id', $course->id)->orderBy('title')->get()
                ->map(fn (Rubric $rubric): array => $rubric->present() + [
                    'description' => $rubric->description,
                    'activity_ids' => $activities->where('rubric_id', $rubric->id)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'assessment_ids' => $assessments->where('rubric_id', $rubric->id)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                ])->values()->all(),
            'activities' => $activities->map(fn (Activity $activity): array => [
                'id' => (int) $activity->id,
                'title' => (string) $activity->title,
                'teacher_marked' => (string) ($activity->pattern?->value ?? $activity->pattern) === 'teacher_marked',
            ])->values()->all(),
            'assessments' => $assessments->map(fn (Assessment $assessment): array => [
                'id' => (int) $assessment->id,
                'title' => (string) $assessment->title,
                'teacher_marked' => (bool) $assessment->requires_teacher_marking,
            ])->values()->all(),
        ];
    }
}
