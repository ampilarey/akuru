<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Legacy\Models\QuizQuestion;

class MapLegacyQuizQuestionAction
{
    /**
     * Map a class-quiz question onto the engine question-bank payload
     * (four activity patterns only).
     *
     * @return array<string, mixed>
     */
    public function execute(QuizQuestion $question): array
    {
        $type = (string) $question->type;
        $options = $this->options($question->options);
        $answer = $question->answer;

        return match ($type) {
            'truefalse' => $this->trueFalse($question, $answer),
            'short' => $this->shortAnswer($question, $answer),
            'essay' => $this->essay($question),
            'matching' => $this->matching($question, $options, $answer),
            default => $this->mcq($question, $options, $answer),
        };
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @return array<string, mixed>
     */
    private function mcq(QuizQuestion $question, array $options, mixed $answer): array
    {
        $ids = $this->answerIds($options, $answer);
        $multiple = count($ids) > 1;

        return [
            'question_type' => $multiple ? 'mcq_multiple' : 'mcq_single',
            'question_text' => (string) $question->body,
            'explanation' => $question->explanation,
            'options' => $options,
            'correct_answer' => $ids,
            'attachments' => $this->attachments($question),
            'settings' => [
                'image_path' => $question->image_path,
                'legacy_type' => $question->type,
            ],
            'legacy_quiz_question_id' => $question->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trueFalse(QuizQuestion $question, mixed $answer): array
    {
        $options = [
            ['id' => 'true', 'label' => 'True'],
            ['id' => 'false', 'label' => 'False'],
        ];
        $truthy = $answer === true || $answer === 'true' || $answer === 'True' || $answer === 0 || $answer === '0';
        $falsy = $answer === false || $answer === 'false' || $answer === 'False' || $answer === 1 || $answer === '1';
        if (is_array($answer) && $answer !== []) {
            $first = $answer[0];
            $truthy = $first === 0 || $first === '0' || $first === true || $first === 'true' || $first === 'True';
            $falsy = $first === 1 || $first === '1' || $first === false || $first === 'false' || $first === 'False';
        }

        return [
            'question_type' => 'true_false',
            'question_text' => (string) $question->body,
            'explanation' => $question->explanation,
            'options' => $options,
            'correct_answer' => [$truthy && ! $falsy ? 'true' : 'false'],
            'attachments' => $this->attachments($question),
            'settings' => [
                'image_path' => $question->image_path,
                'legacy_type' => $question->type,
            ],
            'legacy_quiz_question_id' => $question->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shortAnswer(QuizQuestion $question, mixed $answer): array
    {
        $acceptable = is_array($answer)
            ? array_values(array_map(fn ($row) => trim((string) $row), $answer))
            : [trim((string) $answer)];

        return [
            'question_type' => 'short_answer',
            'question_text' => (string) $question->body,
            'explanation' => $question->explanation,
            'acceptable_answers' => array_values(array_filter($acceptable, fn ($row) => $row !== '')),
            'attachments' => $this->attachments($question),
            'settings' => [
                'image_path' => $question->image_path,
                'legacy_type' => $question->type,
            ],
            'legacy_quiz_question_id' => $question->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function essay(QuizQuestion $question): array
    {
        return [
            'question_type' => 'essay',
            'question_text' => (string) $question->body,
            'explanation' => $question->explanation,
            'attachments' => $this->attachments($question),
            'settings' => [
                'image_path' => $question->image_path,
                'legacy_type' => $question->type,
            ],
            'legacy_quiz_question_id' => $question->id,
        ];
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @return array<string, mixed>
     */
    private function matching(QuizQuestion $question, array $options, mixed $answer): array
    {
        return [
            'question_type' => 'matching',
            'question_text' => (string) $question->body,
            'explanation' => $question->explanation,
            'options' => $options,
            'correct_answer' => is_array($answer) ? array_values($answer) : [$answer],
            'attachments' => $this->attachments($question),
            'settings' => [
                'image_path' => $question->image_path,
                'legacy_type' => $question->type,
            ],
            'legacy_quiz_question_id' => $question->id,
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function options(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $mapped = [];
        foreach (array_values($options) as $index => $option) {
            if (is_array($option) && isset($option['id'], $option['label'])) {
                $mapped[] = ['id' => (string) $option['id'], 'label' => (string) $option['label']];

                continue;
            }
            $mapped[] = ['id' => (string) $index, 'label' => is_scalar($option) ? (string) $option : json_encode($option)];
        }

        return $mapped;
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @return list<string>
     */
    private function answerIds(array $options, mixed $answer): array
    {
        $raw = is_array($answer) ? $answer : [$answer];
        $ids = [];
        foreach ($raw as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $asString = is_scalar($item) ? (string) $item : json_encode($item);
            foreach ($options as $option) {
                if ($option['id'] === $asString || $option['label'] === $asString) {
                    $ids[] = $option['id'];

                    continue 2;
                }
            }
            if (is_numeric($item) && isset($options[(int) $item])) {
                $ids[] = $options[(int) $item]['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachments(QuizQuestion $question): array
    {
        if (! $question->image_path) {
            return [];
        }

        return [['path' => $question->image_path, 'kind' => 'image']];
    }
}
