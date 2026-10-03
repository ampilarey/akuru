<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Rubric;
use App\Domains\Progress\Actions\ReadAttemptItemAction;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use Illuminate\Validation\ValidationException;

/**
 * A teacher marks a submission (SPEC §36). Without a rubric, the score typed
 * is the score, as before. With one (Moodle parity slice M2, STATUS §5oi),
 * the teacher chooses a level for every criterion and the mark follows: the
 * points chosen, out of the rubric's best, scaled to what the item is marked
 * out of. The levels chosen are kept on the attempt as they read today.
 */
class MarkSubmissionAction
{
    /**
     * @param  array<string, mixed>  $data  score, max_score, feedback, item_scores, rubric (criterion id => level id)
     * @param  list<int>|null  $onlyCourseIds
     * @return array<string, mixed>
     */
    public function execute(string $kind, int $attemptId, array $data, int $reviewerId, ?array $onlyCourseIds = null): array
    {
        $rubric = $this->rubricFor($kind, $attemptId);
        if ($rubric !== null) {
            $item = app(ReadAttemptItemAction::class)->execute($kind, $attemptId);
            $outOf = max(1, (int) ($item['max_score'] ?? 0) ?: (int) ($data['max_score'] ?? 0) ?: $rubric->maxPoints());
            $scores = $this->score($rubric, is_array($data['rubric'] ?? null) ? $data['rubric'] : [], $outOf);
            $data = ['score' => $scores['score'], 'max_score' => $outOf, 'rubric_scores' => $scores] + $data;
        }
        unset($data['rubric']);

        return app(ReviewAttemptAction::class)->execute($kind, $attemptId, $data, $reviewerId, $onlyCourseIds);
    }

    public function rubricFor(string $kind, int $attemptId): ?Rubric
    {
        $item = app(ReadAttemptItemAction::class)->execute($kind, $attemptId);
        if ($item === null) {
            return null;
        }
        $rubricId = $kind === 'activity'
            ? Activity::query()->whereKey($item['item_id'])->value('rubric_id')
            : Assessment::query()->whereKey($item['item_id'])->value('rubric_id');

        return $rubricId ? Rubric::query()->find($rubricId) : null;
    }

    /**
     * @param  array<string, mixed>  $chosen  criterion id => level id
     * @return array{rubric_id: int, title: string, criteria: list<array<string, mixed>>, points: int, max_points: int, score: int, out_of: int}
     */
    public function score(Rubric $rubric, array $chosen, int $outOf): array
    {
        $rows = [];
        $points = 0;
        foreach ($rubric->criteria ?? [] as $criterion) {
            $levels = collect($criterion['levels'] ?? []);
            $level = $levels->firstWhere('id', (string) ($chosen[$criterion['id']] ?? ''));
            if ($level === null) {
                throw ValidationException::withMessages([
                    'rubric' => __('teach.rubric_choose_every', ['criterion' => $criterion['title']]),
                ]);
            }
            $points += (int) $level['points'];
            $rows[] = [
                'id' => $criterion['id'],
                'title' => $criterion['title'],
                'level_id' => $level['id'],
                'level' => $level['label'],
                'points' => (int) $level['points'],
                'max_points' => (int) $levels->max('points'),
            ];
        }
        $max = max(1, $rubric->maxPoints());

        return [
            'rubric_id' => (int) $rubric->id,
            'title' => (string) $rubric->title,
            'criteria' => $rows,
            'points' => $points,
            'max_points' => $max,
            'score' => (int) round($points / $max * $outOf),
            'out_of' => $outOf,
        ];
    }
}
