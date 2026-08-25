<?php

namespace App\Domains\Courses\Actions;

class ScoreAssessmentSnapshotsAction
{
    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @param  array<string, mixed>  $answers
     * @return array{score: int, max_score: int, passed: bool, status: string, items: list<array<string, mixed>>}
     */
    public function execute(array $snapshots, array $answers, ?int $passingScore = null): array
    {
        $score = 0;
        $max = 0;
        $needsTeacher = false;
        $items = [];

        foreach ($snapshots as $snapshot) {
            $questionId = (int) ($snapshot['question_id'] ?? 0);
            $points = max(1, (int) ($snapshot['points'] ?? 1));
            $max += $points;
            $given = is_array($answers[(string) $questionId] ?? null)
                ? $answers[(string) $questionId]
                : (is_array($answers[$questionId] ?? null) ? $answers[$questionId] : []);

            $result = app(ScoreActivityAnswersAction::class)->execute([
                'pattern' => $snapshot['pattern'] ?? '',
                'max_score' => $points,
                'data' => [
                    'correct_ids' => $snapshot['correct_answer'] ?? [],
                    'acceptable' => $snapshot['acceptable_answers'] ?? [],
                    'correct_order' => $snapshot['correct_answer'] ?? [],
                    'options' => $snapshot['options'] ?? [],
                ],
                'settings' => [
                    'normalize' => is_array($snapshot['normalization_settings'] ?? null)
                        ? $snapshot['normalization_settings']
                        : [],
                ],
            ], $given);

            if ($result['status'] === 'submitted') {
                $needsTeacher = true;
            } else {
                $score += (int) $result['score'];
            }

            $items[] = [
                'question_id' => $questionId,
                'score' => $result['status'] === 'scored' ? $result['score'] : null,
                'max_score' => $points,
                'status' => $result['status'],
            ];
        }

        $passed = $passingScore === null ? $score === $max && ! $needsTeacher : $score >= $passingScore && ! $needsTeacher;

        return [
            'score' => $score,
            'max_score' => max(1, $max),
            'passed' => $passed,
            'status' => $needsTeacher ? 'submitted' : 'scored',
            'items' => $items,
        ];
    }
}
