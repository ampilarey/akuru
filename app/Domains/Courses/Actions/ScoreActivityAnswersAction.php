<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ActivityPattern;
use Illuminate\Validation\ValidationException;

class ScoreActivityAnswersAction
{
    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $answers
     * @return array{score: int, max_score: int, passed: bool, status: string}
     */
    public function execute(array $activity, array $answers): array
    {
        $pattern = ActivityPattern::tryFrom((string) ($activity['pattern'] ?? ''));
        if ($pattern === null) {
            throw ValidationException::withMessages(['pattern' => 'Unknown activity pattern.']);
        }

        $max = max(1, (int) ($activity['max_score'] ?? 1));
        $passing = $activity['passing_score'] ?? null;

        if ($pattern === ActivityPattern::TeacherMarked) {
            return [
                'score' => 0,
                'max_score' => $max,
                'passed' => false,
                'status' => 'submitted',
            ];
        }

        $score = match ($pattern) {
            ActivityPattern::Selection => $this->scoreSelection($activity['data'] ?? [], $answers, $max),
            ActivityPattern::TextInput => $this->scoreText($activity['data'] ?? [], $activity['settings']['normalize'] ?? [], $answers, $max),
            ActivityPattern::Arrange => $this->scoreArrange($activity['data'] ?? [], $answers, $max),
            ActivityPattern::TeacherMarked => 0,
        };

        $passed = $passing === null ? $score === $max : $score >= (int) $passing;

        return [
            'score' => $score,
            'max_score' => $max,
            'passed' => $passed,
            'status' => 'scored',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $answers
     */
    private function scoreSelection(array $data, array $answers, int $max): int
    {
        $correct = array_values(array_map('strval', $data['correct_ids'] ?? []));
        sort($correct);
        $selected = array_values(array_map('strval', $answers['selected_ids'] ?? []));
        sort($selected);

        return $correct !== [] && $correct === $selected ? $max : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $normalize
     * @param  array<string, mixed>  $answers
     */
    private function scoreText(array $data, array $normalize, array $answers, int $max): int
    {
        $given = app(NormalizeTextAnswerAction::class)->execute((string) ($answers['text'] ?? ''), $normalize);
        foreach ($data['acceptable'] ?? [] as $option) {
            $expected = app(NormalizeTextAnswerAction::class)->execute((string) $option, $normalize);
            if ($given !== '' && $given === $expected) {
                return $max;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $answers
     */
    /**
     * SPEC §17's Pattern 3, "Drag / Arrange Interaction", lists six examples:
     *
     *   > Match pairs · Arrange words · Arrange steps · Sentence builder ·
     *   > Ordering process · Sort items into categories
     *
     * Four of those are **orderings**; two are **mappings**. Only the ordering
     * half was implemented, so "match pairs" and "sort into categories" could
     * not be built — and `QuestionType::Matching` was routed to
     * `ActivityPattern::Selection` instead, where an unordered set of ids is
     * compared and the pairing is never checked at all.
     *
     * The mapping mode is a *configuration* of Pattern 3 rather than a fifth
     * pattern, which is what §17 requires: "New activity types should be added
     * through configuration of these patterns, not through new hardcoded code
     * paths."
     */
    private function scoreArrange(array $data, array $answers, int $max): int
    {
        // Mapping mode: `correct_pairs` is left id => right id. Sorting into
        // categories is the same shape — many lefts may share a right.
        if (isset($data['correct_pairs']) && is_array($data['correct_pairs']) && $data['correct_pairs'] !== []) {
            return $this->scorePairs($data['correct_pairs'], $answers, $max);
        }

        $correct = array_values(array_map('strval', $data['correct_order'] ?? []));
        $given = array_values(array_map('strval', $answers['order'] ?? []));

        return $correct !== [] && $correct === $given ? $max : 0;
    }

    /**
     * @param  array<string, mixed>  $correctPairs
     * @param  array<string, mixed>  $answers
     */
    private function scorePairs(array $correctPairs, array $answers, int $max): int
    {
        $given = is_array($answers['pairs'] ?? null) ? $answers['pairs'] : [];

        $normalise = static function (array $pairs): array {
            $out = [];
            foreach ($pairs as $left => $right) {
                // A right-hand side that was never chosen is not an answer;
                // keeping it would let an empty submission match an empty key.
                if ($right === null || $right === '') {
                    continue;
                }
                $out[(string) $left] = (string) $right;
            }
            ksort($out);

            return $out;
        };

        $correct = $normalise($correctPairs);
        $student = $normalise($given);

        // All or nothing, matching how ordering and selection already score.
        // Partial credit across the four patterns is a separate decision, and
        // making only this one generous would be the inconsistent choice.
        return $correct !== [] && $correct === $student ? $max : 0;
    }
}
