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
            'acceptable_answers' => $question->acceptable_answers,
            'normalization_settings' => $question->normalization_settings,
            'attachments' => $question->attachments,
            'difficulty' => $question->difficulty,
            'skill_tag' => $question->skill_tag,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
