<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;

class ListPublishedCoursesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(?int $unifiedStudentId = null): array
    {
        $enrolled = $unifiedStudentId
            ? CourseEnrollment::query()
                ->where('unified_student_id', $unifiedStudentId)
                ->whereIn('status', ['active', 'approved', 'completed'])
                ->pluck('progress_percentage', 'course_id')
            : collect();

        return Course::query()
            ->where('workflow_status', CourseWorkflowStatus::Published)
            ->orderBy('title')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'short_desc' => $course->short_desc,
                'enrolled' => $enrolled->has($course->id),
                'progress_percentage' => (int) ($enrolled[$course->id] ?? 0),
            ])
            ->all();
    }
}
