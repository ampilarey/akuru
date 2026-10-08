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

        $attachments = is_array($data['attachments'] ?? null)
            ? array_values(array_filter($data['attachments'], 'is_array'))
            : ($question?->attachments ?? []);
        $media = app(ResolveQuestionMediaAction::class);

        $file = $data['file'] ?? null;
        if ($file instanceof UploadedFile) {
            // SPEC §30 sets the allowed mimes and a size cap **per kind of
            // media**, and both already live on `ContentBlockType`. This call
            // passed neither, so a question attachment was the one upload path
            // in the app with no type check and no size limit at all — a 400MB
            // `.exe` was a valid question attachment.
            $kind = $media->assertSupportedMime(
                (string) ($file->getMimeType() ?: $file->getClientMimeType()),
            );

            $stored = app(StorePrivateMediaAction::class)->execute(
                $file,
                isset($data['created_by']) ? (int) $data['created_by'] : null,
                $kind->allowedMimes(),
                $kind->maxBytes(),
            );
            $attachments[] = [
                'media_id' => $stored['id'],
                'mime' => $stored['mime'],
                'kind' => $kind->value,
                'original_name' => $stored['original_name'],
            ];
        }

        // §20's fourth attachment kind is a "Video reference", not an upload.
        // It shares §15's host allowlist rather than carrying its own.
        $videoUrl = trim((string) ($data['video_url'] ?? ''));
        if ($videoUrl !== '') {
            $attachments[] = [
                'embed_url' => app(NormalizeVideoEmbedUrlAction::class)->execute($videoUrl, 'video_url'),
                'kind' => 'video',
                'original_name' => $data['video_title'] ?? null,
            ];
        }

        if (isset($data['remove_attachment'])) {
            $index = (int) $data['remove_attachment'];
            unset($attachments[$index]);
            $attachments = array_values($attachments);
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
            'correct_answer' => $type === QuestionType::Matching
                ? $this->answerPairs($data['correct_answer'] ?? null)
                : $this->jsonList($data['correct_answer'] ?? null),
            'acceptable_answers' => $this->stringList($data['acceptable_answers'] ?? null),
            // SPEC §18: an unknown or misspelled switch used to be stored and
            // then silently ignored at scoring time, so the question marked
            // leniently while its settings claimed otherwise.
            'normalization_settings' => app(ValidateNormalizationSettingsAction::class)
                ->execute($data['normalization_settings'] ?? null),
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
     * A matching question's answer key: each left item's id paired with its
     * match (SPEC §17 Pattern 3, mapping mode) — `{"1": "Alif", "2": "Baa"}`,
     * the very key the bank's form suggests.
     *
     * It went through `jsonList`, which keeps only the values — right for
     * every other key, and fatal to this one. A pairing saved from the bank
     * came back as `["Alif", "Baa"]`, the snapshot found no right-hand column,
     * and the learner was shown an ordering with Up and Down buttons. The
     * pairs were gone, and nothing told the author.
     *
     * A list still passes as a list: the legacy quiz import hands its
     * matching keys over that way, and refusing them would stop the import.
     * A pairing numbered 0, 1, 2… is refused instead, because PHP reads those
     * keys back as a list and the column would store the same lost pairing.
     *
     * @return array<int|string, mixed>|null
     */
    private function answerPairs(mixed $value): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value);
            if (! $decoded instanceof \stdClass) {
                return $this->jsonList($value);
            }
            $value = (array) $decoded;
            if ($value !== [] && array_is_list($value)) {
                throw ValidationException::withMessages([
                    'correct_answer' => 'Number the items from 1, or name them: a pairing keyed 0, 1, 2… is read back as a list and loses its pairs.',
                ]);
            }
        }

        if (! is_array($value)) {
            return null;
        }
        if ($value === [] || array_is_list($value)) {
            return array_values($value);
        }

        foreach ($value as $match) {
            if (! is_scalar($match)) {
                throw ValidationException::withMessages([
                    'correct_answer' => 'Each item is paired with one match, written as text.',
                ]);
            }
        }

        return $value;
    }

    /**
     * SPEC §18 "Accept multiple correct answers". Accepts either a JSON array
     * or one answer per line.
     *
     * The line form is not a convenience: `jsonList` turns any string that
     * fails to decode into `[]`, so a plainly-typed list of accepted answers
     * was silently saved as none at all — and a text question then marked only
     * its single `correct_answer`, with nothing reported to the author.
     *
     * @return list<string>|null
     */
    private function stringList(mixed $value): ?array
    {
        if (is_string($value) && trim($value) !== '' && json_decode($value, true) === null) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

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
