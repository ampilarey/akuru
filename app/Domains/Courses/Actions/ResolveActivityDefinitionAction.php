<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use Illuminate\Validation\ValidationException;

class ResolveActivityDefinitionAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $activityId, bool $includeAnswerKeys = false): array
    {
        $activity = Activity::query()->find($activityId);
        if ($activity === null) {
            throw ValidationException::withMessages([
                'activity' => ['Activity not found.'],
            ]);
        }

        return app(ListCourseActivitiesAction::class)->serialize($activity, $includeAnswerKeys);
    }
}
