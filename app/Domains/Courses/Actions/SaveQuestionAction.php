<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\QuestionType;
use App\Domains\Courses\Models\Question;
use App\Domains\ExamsGrades\Actions\SyncStandardTagsAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SaveQuestionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Question $question = null): Question
    {
        $type = QuestionType::tryFrom((string) ($data['question_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages([
                'question_type' => 'Unknown question type.',
            ]);
        }

        $text = trim((string) ($data['question_text'] ?? ''));
        if ($text === '') {
            throw ValidationException::withMessages([
                'question_text' => 'Question text is required.',
            ]);
        }

        if ($question === null && ! empty($data['legacy_quiz_question_id'])) {
            $question = Question::query()->where('legacy_quiz_question_id', (int) $data['legacy_quiz_question_id'])->first();
        }
        if ($question === null && ! empty($data['legacy_assignment_id'])) {
            $question = Question::query()->where('legacy_assignment_id', (int) $data['legacy_assignment_id'])->first();
        }

        $attachments = is_array($data['attachments'] ?? null) ? $data['attachments'] : [];
        $file = $data['file'] ?? null;
        if ($file instanceof UploadedFile) {
            $stored = app(StorePrivateMediaAction::class)->execute(
                $file,
                isset($data['created_by']) ? (int) $data['created_by'] : null,
            );
            $attachments[] = [
                'media_id' => $stored['id'],
                'mime' => $stored['mime'],
                'original_name' => $stored['original_name'],
            ];
        }

        $payload = [
            'subject_id' => $data['subject_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'question_type' => $type,
            'pattern' => $type->pattern(),
            'title' => $data['title'] ?? null,
            'question_text' => $text,
            'secondary_text' => $data['secondary_text'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'options' => $this->jsonList($data['options'] ?? null),
            'correct_answer' => $this->jsonList($data['correct_answer'] ?? null),
            'acceptable_answers' => $this->stringList($data['acceptable_answers'] ?? null),
            'normalization_settings' => is_array($data['normalization_settings'] ?? null)
                ? $data['normalization_settings']
                : null,
            'difficulty' => in_array($difficulty = (string) ($data['difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true)
                ? $difficulty
                : 'medium',
            'skill_tag' => $data['skill_tag'] ?? null,
            'attachments' => $attachments,
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'created_by' => $data['created_by'] ?? $question?->created_by,
            'legacy_quiz_question_id' => isset($data['legacy_quiz_question_id'])
                ? (int) $data['legacy_quiz_question_id']
                : $question?->legacy_quiz_question_id,
            'legacy_assignment_id' => isset($data['legacy_assignment_id'])
                ? (int) $data['legacy_assignment_id']
                : $question?->legacy_assignment_id,
        ];

        if ($question === null) {
            $question = Question::query()->create($payload);
        } else {
            $question->fill($payload);
            $question->save();
        }

        $standardIds = $data['standard_ids'] ?? [];
        if (is_string($standardIds)) {
            $standardIds = array_filter(array_map('trim', explode(',', $standardIds)));
        }
        app(SyncStandardTagsAction::class)->execute(
            'question',
            $question->id,
            is_array($standardIds) ? $standardIds : [],
        );

        return $question->fresh();
    }

    /**
     * @return list<mixed>|null
     */
    private function jsonList(mixed $value): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? array_values($value) : null;
    }

    /**
     * @return list<string>|null
     */
    private function stringList(mixed $value): ?array
    {
        $list = $this->jsonList($value);
        if ($list === null) {
            return null;
        }

        return array_values(array_filter(array_map(
            fn ($row) => trim((string) $row),
            $list,
        )));
    }
}
