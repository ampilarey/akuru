<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Progress\Actions\ListActivityAttemptsByIdsAction;
use Illuminate\Support\Collection;

class ListArabicSkillReportAction
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, letters: Collection<int, array<string, mixed>>, harakas: Collection<int, array<string, mixed>>}
     */
    public function execute(?int $courseId = null, ?int $enrollmentId = null): array
    {
        $reference = app(ListArabicReferenceAction::class)->execute();
        $letters = $reference['letters']->keyBy('id');
        $harakas = $reference['harakas']->keyBy('id');

        $query = Activity::query()->whereNotNull('settings->skill')->orderBy('id');
        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }
        $activities = $query->get();
        $attempts = app(ListActivityAttemptsByIdsAction::class)
            ->execute($activities->pluck('id')->all(), $enrollmentId)
            ->groupBy('activity_id');

        $rows = $activities->map(function (Activity $activity) use ($attempts, $letters, $harakas): array {
            $settings = $activity->settings ?? [];
            $rows = $attempts->get($activity->id, collect());
            $scored = $rows->where('status', 'scored');

            return [
                'activity_id' => $activity->id,
                'course_id' => $activity->course_id,
                'title' => $activity->title,
                'pattern' => $activity->pattern->value,
                'skill' => $settings['skill'] ?? null,
                'letter' => $letters->get($settings['letter_id'] ?? null),
                'harakah' => $harakas->get($settings['harakah_id'] ?? null),
                'attempts' => $rows->count(),
                'average_score' => $scored->count() > 0
                    ? (int) round($scored->avg('score'))
                    : null,
                'latest_status' => $rows->first()['status'] ?? null,
            ];
        })->values();

        return [
            'rows' => $rows,
            'letters' => $reference['letters'],
            'harakas' => $reference['harakas'],
        ];
    }
}
