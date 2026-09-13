<?php

namespace App\Domains\Courses\Actions;

/**
 * SPEC §21 gives `assessment_questions` an **Is required** column.
 *
 * It is stored, defaulted to true, and copied faithfully into every attempt
 * snapshot by `BuildAssessmentSnapshotsAction`:
 *
 *     $snapshot['is_required'] = (bool) $row->is_required;
 *
 * And then **nothing reads it**. Not the submit path, not the scorer, not the
 * player — a search across the app finds readers for `ContentBlock`'s
 * `is_required` and for the lesson-glossary pivot's, and none at all for this
 * one. So "required" meant nothing: a student could submit an assessment with
 * every required question blank and be scored zero on them without ever being
 * told they had skipped anything.
 *
 * Which answers "count" is the scorer's question, not this Action's invention —
 * `ScoreAssessmentSnapshotsAction` reads `selected_ids`, `order`, `pairs` and
 * `text` per pattern, and an answer is present here exactly when one of those
 * carries something. Two definitions of "answered" would be worse than none.
 */
class ListUnansweredRequiredQuestionsAction
{
    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @param  array<string, mixed>  $answers
     * @return list<string> question titles or texts, in the order asked
     */
    public function execute(array $snapshots, array $answers): array
    {
        $missing = [];

        foreach ($snapshots as $snapshot) {
            if (! ($snapshot['is_required'] ?? false)) {
                continue;
            }

            $questionId = (int) ($snapshot['question_id'] ?? 0);
            $given = $this->given($answers, $questionId);

            if ($this->isAnswered($given)) {
                continue;
            }

            $label = trim((string) ($snapshot['title'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($snapshot['question_text'] ?? ''));
            }

            $missing[] = $label !== '' ? $label : 'Question '.$questionId;
        }

        return $missing;
    }

    /**
     * Answers arrive keyed by question id, and JSON round-trips turn integer
     * keys into strings — the scorer looks both ways for the same reason.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function given(array $answers, int $questionId): array
    {
        if (is_array($answers[(string) $questionId] ?? null)) {
            return $answers[(string) $questionId];
        }

        return is_array($answers[$questionId] ?? null) ? $answers[$questionId] : [];
    }

    /**
     * @param  array<string, mixed>  $given
     */
    private function isAnswered(array $given): bool
    {
        if (! empty($given['selected_ids']) && is_array($given['selected_ids'])) {
            return true;
        }
        if (! empty($given['order']) && is_array($given['order'])) {
            return true;
        }
        // A mapping question is answered only when some pair was actually
        // chosen: the player seeds `pairs` as an empty object, and an empty
        // object is the absence of an answer rather than a wrong one.
        if (is_array($given['pairs'] ?? null) && array_filter($given['pairs'], fn ($value): bool => trim((string) $value) !== '') !== []) {
            return true;
        }

        return trim((string) ($given['text'] ?? '')) !== '';
    }
}
