<?php

namespace App\Domains\Courses\Actions;

use Illuminate\Support\Collection;

/**
 * Audience and level, for the domain that stores them but may not read their
 * models.
 *
 * SPEC §10.5/§10.6 put `audience_id` and `level_id` on `course_offerings`, but
 * both taxonomies belong to Courses. Rule 3 lets Offerings reach Courses only
 * through Contracts/DTOs/Events/**Actions** — and `BaselineArchitectureTest`
 * enforces exactly that, plus `Phase1ABoundariesTest` fails outright if any
 * file under `app/Domains/Offerings` so much as names a Courses model. So
 * there is no Eloquent relation from `CourseOffering` to these tables; the ids
 * are stored plainly and resolved here.
 *
 * The same seam the §38 payment-method slice arrived at, for the same reason.
 */
class ResolveOfferingTaxonomyAction
{
    /**
     * The option lists a form needs, already in the shape a select renders.
     *
     * @return array{audiences: list<array{id: int, label: string}>, levels: list<array{id: int, label: string}>}
     */
    public function options(): array
    {
        return [
            'audiences' => $this->options_from(app(ListAudiencesAction::class)->execute()),
            'levels' => $this->options_from(app(ListCourseLevelsAction::class)->execute()),
        ];
    }

    /**
     * Labels for one offering's stored ids, or null where nothing is set.
     *
     * @return array{audience: ?string, level: ?string}
     */
    public function labels(?int $audienceId, ?int $levelId): array
    {
        $byId = static fn (array $options, ?int $id): ?string => $id === null
            ? null
            : (collect($options)->firstWhere('id', $id)['label'] ?? null);

        $options = $this->options();

        return [
            'audience' => $byId($options['audiences'], $audienceId),
            'level' => $byId($options['levels'], $levelId),
        ];
    }

    /**
     * Whether an id names a row that exists and is still active.
     *
     * Deactivating an audience must not silently blank it on the offerings
     * already using it, so this is only asked of *incoming* values.
     */
    public function audienceExists(?int $id): bool
    {
        return $id === null || collect($this->options()['audiences'])->contains('id', $id);
    }

    public function levelExists(?int $id): bool
    {
        return $id === null || collect($this->options()['levels'])->contains('id', $id);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{id: int, label: string}>
     */
    private function options_from(Collection $rows): array
    {
        return $rows
            ->filter(fn (array $row): bool => (bool) ($row['active'] ?? true))
            ->map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'label' => (string) $row['name_en'],
            ])
            ->values()
            ->all();
    }
}
