<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Question;

class SnapshotQuestionAction
{
    /**
     * Frozen copy used by assessment attempts. Editing the live question
     * must never mutate a previously returned snapshot.
     *
     * @return array<string, mixed>
     */
    public function execute(Question $question): array
    {
        return [
            'question_id' => $question->id,
            'question_type' => $question->question_type->value,
            'pattern' => $question->pattern->value,
            'title' => $question->title,
            'question_text' => $question->question_text,
            'secondary_text' => $question->secondary_text,
            'explanation' => $question->explanation,
            'options' => $question->options,
            'correct_answer' => $question->correct_answer,
            // SPEC §17 Pattern 3 mapping questions ("match pairs", "sort into
            // categories") need their right-hand column visible — that is the
            // question, not the answer. It is computed here rather than in the
            // client because `correct_answer` is stripped from the student's
            // snapshot, so the client has nothing to derive it from and must
            // not be handed the key to do so.
            'targets' => $this->targets($question),
            'acceptable_answers' => $question->acceptable_answers,
            'normalization_settings' => $question->normalization_settings,
            'attachments' => $question->attachments,
            'difficulty' => $question->difficulty,
            'skill_tag' => $question->skill_tag,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The right-hand choices for a mapping question, in a stable order.
     *
     * Sorted rather than left in key order: the order `correct_answer` happens
     * to be written in would otherwise hint at the pairing.
     *
     * @return list<array{id: string, label: string}>|null
     */
    private function targets(Question $question): ?array
    {
        $key = $question->correct_answer;

        if (! is_array($key) || $key === [] || array_is_list($key)) {
            return null;
        }

        $values = array_values(array_unique(array_map('strval', $key)));
        sort($values);

        return array_map(static fn (string $value): array => [
            'id' => $value,
            'label' => $value,
        ], $values);
    }
}
