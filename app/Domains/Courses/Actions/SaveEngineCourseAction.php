<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveEngineCourseAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Course $course = null): Course
    {
        if ($course !== null && $course->workflow_status !== CourseWorkflowStatus::Draft) {
            throw ValidationException::withMessages([
                'workflow_status' => 'Only draft courses can be edited.',
            ]);
        }

        $title = (string) $data['title'];
        $payload = [
            'subject_id' => isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null,
            'title' => $title,
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'slug' => $data['slug'] ?? Str::slug($title).'-'.Str::lower(Str::random(6)),
            'short_desc' => $data['short_desc'] ?? $title,
            'body' => $data['body'] ?? $title,
            'cover_image' => $data['cover_image'] ?? '',
            'language' => $data['language'] ?? 'en',
            'course_type' => $data['course_type'] ?? 'general',
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($course === null) {
            $payload['workflow_status'] = CourseWorkflowStatus::Draft;
            $payload['status'] = 'closed';
            $payload['course_category_id'] = CourseCategory::query()->value('id')
                ?? CourseCategory::query()->create([
                    'name' => 'General',
                    'slug' => 'general',
                    'order' => 0,
                ])->id;

            return Course::query()->create($payload);
        }

        $course->fill($payload);
        $course->save();

        return $course->refresh();
    }
}
