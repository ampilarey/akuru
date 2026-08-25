<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Question;
use App\Domains\ExamsGrades\Actions\ListStandardTagsAction;
use Illuminate\Support\Collection;

class ListQuestionsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $query = Question::query()->orderByDesc('id');

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', (int) $filters['subject_id']);
        }
        if (! empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }
        if (! empty($filters['question_type'])) {
            $query->where('question_type', (string) $filters['question_type']);
        }

        return $query->get()->map(fn (Question $question): array => $this->serialize($question));
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Question $question): array
    {
        return [
            'id' => $question->id,
            'subject_id' => $question->subject_id,
            'category_id' => $question->category_id,
            'course_id' => $question->course_id,
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
            'difficulty' => $question->difficulty,
            'skill_tag' => $question->skill_tag,
            'attachments' => $question->attachments,
            'standard_ids' => app(ListStandardTagsAction::class)->execute('question', $question->id)->all(),
        ];
    }
}
