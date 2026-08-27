<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use Illuminate\Support\Collection;

/**
 * Engine-owned seam for components (F0). Components must not import engine
 * models (rule 3), so subject reports consume these plain arrays instead of
 * Activity — the engine keeps its schema private and stays subject-ignorant.
 *
 * @see \App\Domains\Courses\Components\Arabic\Actions\ListArabicSkillReportAction
 */
class ListSkillTaggedActivitiesAction
{
    /**
     * Activities carrying a settings->skill tag, oldest first.
     *
     * @return Collection<int, array{id: int, course_id: int|null, title: string, pattern: string, settings: array<string, mixed>}>
     */
    public function execute(?int $courseId = null): Collection
    {
        return Activity::query()
            ->whereNotNull('settings->skill')
            ->when($courseId !== null, fn ($query) => $query->where('course_id', $courseId))
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => (int) $activity->id,
                'course_id' => $activity->course_id !== null ? (int) $activity->course_id : null,
                'title' => (string) $activity->title,
                'pattern' => $activity->pattern->value,
                'settings' => $activity->settings ?? [],
            ])
            ->values();
    }
}
