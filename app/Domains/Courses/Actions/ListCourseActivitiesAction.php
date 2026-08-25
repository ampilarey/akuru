<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Course;
use Illuminate\Support\Collection;

class ListCourseActivitiesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Course $course, bool $includeAnswerKeys = true): Collection
    {
        return Activity::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $activity): array => $this->serialize($activity, $includeAnswerKeys));
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Activity $activity, bool $includeAnswerKeys = true): array
    {
        $data = $activity->data;
        if (! $includeAnswerKeys) {
            unset($data['correct_ids'], $data['acceptable'], $data['correct_order']);
        }

        return [
            'id' => $activity->id,
            'course_id' => $activity->course_id,
            'course_module_id' => $activity->course_module_id,
            'lesson_id' => $activity->lesson_id,
            'title' => $activity->title,
            'description' => $activity->description,
            'pattern' => $activity->pattern->value,
            'activity_type' => $activity->activity_type,
            'data' => $data,
            'settings' => $activity->settings,
            'max_score' => (int) $activity->max_score,
            'passing_score' => $activity->passing_score !== null ? (int) $activity->passing_score : null,
            'is_required' => (bool) $activity->is_required,
        ];
    }
}
