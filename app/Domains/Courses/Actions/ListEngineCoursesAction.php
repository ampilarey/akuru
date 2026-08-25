<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use Illuminate\Support\Collection;

class ListEngineCoursesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        $subjects = app(ListCourseSubjectsAction::class)->execute()->keyBy('id');

        return Course::query()
            ->orderBy('title')
            ->get()
            ->map(function (Course $course) use ($subjects): array {
                $subject = $subjects->get($course->subject_id);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'title_dv' => $course->title_dv,
                    'title_ar' => $course->title_ar,
                    'slug' => $course->slug,
                    'subject_id' => $course->subject_id,
                    'subject_name' => $subject['name_en'] ?? '',
                    'language' => $course->language,
                    'course_type' => $course->course_type,
                    'workflow_status' => $course->workflow_status?->value ?? $course->workflow_status,
                    'marketing_status' => $course->status,
                ];
            })->values();
    }
}
