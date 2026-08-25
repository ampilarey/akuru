<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\LessonStatus;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveLessonAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Lesson $lesson = null): Lesson
    {
        $module = CourseModule::query()->findOrFail((int) $data['course_module_id']);
        $title = (string) $data['title'];
        $slug = $data['slug'] ?? Str::slug($title);
        if ($slug === '') {
            $slug = 'lesson-'.Str::lower(Str::random(6));
        }

        $exists = Lesson::query()
            ->where('course_id', $module->course_id)
            ->where('slug', $slug)
            ->when($lesson, fn ($query) => $query->where('id', '!=', $lesson->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['slug' => 'Lesson slug must be unique within the course.']);
        }

        $payload = [
            'course_id' => $module->course_id,
            'course_module_id' => $module->id,
            'title' => $title,
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'position' => (int) ($data['position'] ?? ((Lesson::query()->where('course_module_id', $module->id)->max('position') ?? -1) + 1)),
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'is_preview' => (bool) ($data['is_preview'] ?? false),
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($lesson === null) {
            $payload['status'] = LessonStatus::Draft;

            return Lesson::query()->create($payload);
        }

        $lesson->fill($payload);
        $lesson->save();

        return $lesson->refresh();
    }
}
