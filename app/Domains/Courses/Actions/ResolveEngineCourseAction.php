<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;

class ResolveEngineCourseAction
{
    /**
     * @return array{id: int, title: string, workflow_status: string}
     */
    public function execute(int $courseId): array
    {
        $course = Course::query()->findOrFail($courseId);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'workflow_status' => $course->workflow_status?->value ?? (string) $course->workflow_status,
        ];
    }
}
