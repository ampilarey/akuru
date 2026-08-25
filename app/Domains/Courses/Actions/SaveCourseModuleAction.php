<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseModule;

class SaveCourseModuleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseModule $module = null): CourseModule
    {
        $payload = [
            'course_id' => (int) $data['course_id'],
            'title' => $data['title'],
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'description' => $data['description'] ?? null,
            'position' => (int) ($data['position'] ?? ((CourseModule::query()->where('course_id', $data['course_id'])->max('position') ?? -1) + 1)),
            'status' => $data['status'] ?? 'draft',
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($module === null) {
            return CourseModule::query()->create($payload);
        }

        $module->fill($payload);
        $module->save();

        return $module->refresh();
    }
}
