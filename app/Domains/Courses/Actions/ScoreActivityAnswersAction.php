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
    private function scoreArrange(array $data, array $answers, int $max): int
    {
        $correct = array_values(array_map('strval', $data['correct_order'] ?? []));
        $given = array_values(array_map('strval', $answers['order'] ?? []));

        return $correct !== [] && $correct === $given ? $max : 0;
    }
}
