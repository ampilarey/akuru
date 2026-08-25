<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseModule;

class ListCourseOutlineAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $courseId): array
    {
        $course = Course::query()->findOrFail($courseId);
        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->with(['lessons.blocks', 'lessons.currentRevision'])
            ->orderBy('position')
            ->get();

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'workflow_status' => $course->workflow_status?->value ?? $course->workflow_status,
            ],
            'modules' => $modules->map(fn (CourseModule $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'position' => $module->position,
                'lessons' => $module->lessons->map(fn ($lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'slug' => $lesson->slug,
                    'status' => $lesson->status?->value ?? $lesson->status,
                    'current_revision_id' => $lesson->current_revision_id,
                    'revision_number' => $lesson->currentRevision?->revision_number,
                    'blocks' => $lesson->blocks->map(fn ($block) => [
                        'id' => $block->id,
                        'type' => $block->type,
                        'position' => $block->position,
                        'title' => $block->title,
                        'data' => $block->data,
                        'settings' => $block->settings,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
