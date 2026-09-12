<?php

namespace App\Domains\Courses\Actions;

class ScoreAssessmentSnapshotsAction
{
    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @param  array<string, mixed>  $answers
     * @return array{score: int, max_score: int, passed: bool, status: string, items: list<array<string, mixed>>}
     */
    /**
     * @param  bool  $requiresTeacherMarking  SPEC §19: the assessment-level flag.
     *                                        Auto-scorable questions still get their marks — the
     *                                        teacher is not made to re-do arithmetic — but the
     *                                        attempt does not become final until a human says so.
     */
    public function execute(array $snapshots, array $answers, ?int $passingScore = null, bool $requiresTeacherMarking = false): array
    {
        $score = 0;
        $max = 0;
        // Per-question marking was the only way an attempt could be held for a
        // teacher: it waited only if some question's pattern could not be
        // auto-scored. So a speaking or writing assessment built out of
        // multiple-choice questions was scored and finalised with no teacher
        // involved — and with `show_correct_answers` on, the student was handed
        // the answer key too.
        $needsTeacher = $requiresTeacherMarking;
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
                    // SPEC §17 Pattern 3 covers mappings as well as orderings
                    // ("Match pairs", "Sort items into categories"). A matching
                    // question's `correct_answer` is a map of left id => right
                    // value; an ordering's is a list. The shape tells them
                    // apart, so no new snapshot field is needed and older
                    // snapshots keep scoring exactly as before.
                    'correct_pairs' => $this->pairsOrNothing($snapshot['correct_answer'] ?? null),
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

    /**
     * A matching question's `correct_answer` is a map (left id => right value);
     * an ordering question's is a list. Only the map shape is a pairing, so an
     * existing arrange question keeps scoring by order exactly as before.
     *
     * @param  mixed  $correctAnswer
     * @return array<string, mixed>
     */
    private function pairsOrNothing($correctAnswer): array
    {
        if (! is_array($correctAnswer) || $correctAnswer === []) {
            return [];
        }

        return array_is_list($correctAnswer) ? [] : $correctAnswer;
    }
}
