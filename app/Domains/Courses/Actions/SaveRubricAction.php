<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\Rubric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Moodle parity slice M2 (STATUS §5oi): create or edit a course's rubric, and
 * say which of the course's activities and assessments it marks.
 *
 * Criteria keep their ids across edits (the form sends them back), so a
 * criterion renamed is the same criterion. A mark already given is a snapshot
 * on the attempt and does not move when the rubric does.
 */
class SaveRubricAction
{
    public const MAX_CRITERIA = 20;

    public const MAX_LEVELS = 8;

    public const MAX_POINTS = 100;

    /**
     * @param  array{title: string, description?: ?string, criteria: array<int, array<string, mixed>>, activity_ids?: array<int, mixed>, assessment_ids?: array<int, mixed>}  $data
     */
    public function execute(Course $course, array $data, ?int $actorId, ?Rubric $rubric = null): Rubric
    {
        $criteria = $this->criteria($data['criteria'] ?? []);

        return DB::transaction(function () use ($course, $data, $actorId, $rubric, $criteria): Rubric {
            $payload = [
                'title' => trim((string) $data['title']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'criteria' => $criteria,
            ];
            if ($rubric === null) {
                $rubric = Rubric::query()->create($payload + ['course_id' => $course->id, 'created_by' => $actorId]);
            } else {
                $rubric->update($payload);
            }

            $this->use(Activity::class, $course, $rubric, $data['activity_ids'] ?? []);
            $this->use(Assessment::class, $course, $rubric, $data['assessment_ids'] ?? []);

            return $rubric->refresh();
        });
    }

    /**
     * The items chosen use this rubric; the items that used it and are no
     * longer chosen use none. Only this course's items are ever touched.
     *
     * @param  class-string<Activity|Assessment>  $model
     * @param  array<int, mixed>  $ids
     */
    private function use(string $model, Course $course, Rubric $rubric, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $model::query()->where('course_id', $course->id)->where('rubric_id', $rubric->id)->whereNotIn('id', $ids ?: [0])->update(['rubric_id' => null]);
        if ($ids !== []) {
            $model::query()->where('course_id', $course->id)->whereIn('id', $ids)->update(['rubric_id' => $rubric->id]);
        }
    }

    /**
     * @param  array<int, mixed>  $input
     * @return list<array{id: string, title: string, levels: list<array{id: string, label: string, points: int}>}>
     */
    private function criteria(array $input): array
    {
        $criteria = [];
        foreach (array_values($input) as $i => $criterion) {
            if (! is_array($criterion)) {
                continue;
            }
            $title = trim((string) ($criterion['title'] ?? ''));
            $levels = [];
            foreach (array_values(is_array($criterion['levels'] ?? null) ? $criterion['levels'] : []) as $level) {
                $label = trim((string) ($level['label'] ?? ''));
                if ($label === '' || ! is_numeric($level['points'] ?? null)) {
                    continue;
                }
                $levels[] = [
                    'id' => $this->id($level['id'] ?? null),
                    'label' => Str::limit($label, 255, ''),
                    'points' => max(0, min(self::MAX_POINTS, (int) $level['points'])),
                ];
            }
            if ($title === '' && $levels === []) {
                continue;
            }
            if ($title === '' || count($levels) < 2) {
                throw ValidationException::withMessages([
                    "criteria.$i" => __('teach.rubric_criterion_invalid', ['n' => $i + 1]),
                ]);
            }
            usort($levels, fn (array $a, array $b): int => $a['points'] <=> $b['points']);
            $criteria[] = ['id' => $this->id($criterion['id'] ?? null), 'title' => Str::limit($title, 255, ''), 'levels' => array_slice($levels, 0, self::MAX_LEVELS)];
        }

        if ($criteria === [] || count($criteria) > self::MAX_CRITERIA) {
            throw ValidationException::withMessages(['criteria' => __('teach.rubric_needs_criteria', ['max' => self::MAX_CRITERIA])]);
        }

        return $criteria;
    }

    private function id(mixed $id): string
    {
        $id = is_string($id) ? preg_replace('/[^A-Za-z0-9_-]/', '', $id) : '';

        return $id !== '' ? Str::limit($id, 32, '') : Str::lower(Str::random(10));
    }
}
