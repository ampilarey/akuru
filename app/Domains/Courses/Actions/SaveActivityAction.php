<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ActivityPattern;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Course;
use Illuminate\Validation\ValidationException;

class SaveActivityAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Activity $activity = null): Activity
    {
        $course = Course::query()->findOrFail((int) $data['course_id']);
        $pattern = ActivityPattern::tryFrom((string) ($data['pattern'] ?? ''));
        if ($pattern === null) {
            throw ValidationException::withMessages(['pattern' => 'Activity must use one of the four base patterns.']);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Activity title is required.']);
        }

        $payload = [
            'course_id' => $course->id,
            'course_module_id' => $data['course_module_id'] ?? $data['module_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'pattern' => $pattern,
            'activity_type' => (string) (($data['activity_type'] ?? '') ?: $pattern->value),
            'data' => $this->validatedData($pattern, is_array($data['data'] ?? null) ? $data['data'] : []),
            'settings' => $this->validatedSettings(is_array($data['settings'] ?? null) ? $data['settings'] : []),
            'max_score' => max(1, (int) ($data['max_score'] ?? 1)),
            'passing_score' => isset($data['passing_score']) && $data['passing_score'] !== '' ? (int) $data['passing_score'] : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($activity === null) {
            return Activity::query()->create($payload);
        }

        $activity->fill($payload);
        $activity->save();

        return $activity->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedData(ActivityPattern $pattern, array $data): array
    {
        return match ($pattern) {
            ActivityPattern::Selection => $this->selectionData($data),
            ActivityPattern::TextInput => $this->textData($data),
            ActivityPattern::Arrange => $this->arrangeData($data),
            ActivityPattern::TeacherMarked => [
                'prompt' => trim((string) ($data['prompt'] ?? '')),
                'submission_kind' => in_array($data['submission_kind'] ?? 'written', ['written', 'file'], true)
                    ? $data['submission_kind']
                    : 'written',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function selectionData(array $data): array
    {
        $options = [];
        foreach (is_array($data['options'] ?? null) ? $data['options'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($id !== '' && $label !== '') {
                $options[] = ['id' => $id, 'label' => $label];
            }
        }
        $correct = array_values(array_filter(array_map('strval', $data['correct_ids'] ?? [])));
        if ($options === [] || $correct === []) {
            throw ValidationException::withMessages(['data' => 'Selection activities need options and at least one correct id.']);
        }

        return [
            'prompt' => trim((string) ($data['prompt'] ?? '')),
            'options' => $options,
            'correct_ids' => $correct,
            'multiple' => (bool) ($data['multiple'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function textData(array $data): array
    {
        $acceptable = array_values(array_filter(array_map(
            fn ($row) => trim((string) $row),
            is_array($data['acceptable'] ?? null) ? $data['acceptable'] : [],
        )));
        if ($acceptable === []) {
            throw ValidationException::withMessages(['data' => 'Text-input activities need at least one acceptable answer.']);
        }

        return [
            'prompt' => trim((string) ($data['prompt'] ?? '')),
            'acceptable' => $acceptable,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function arrangeData(array $data): array
    {
        $items = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($id !== '' && $label !== '') {
                $items[] = ['id' => $id, 'label' => $label];
            }
        }
        $order = array_values(array_filter(array_map('strval', $data['correct_order'] ?? [])));
        if (count($items) < 2 || $order === []) {
            throw ValidationException::withMessages(['data' => 'Arrange activities need at least two items and a correct order.']);
        }

        return [
            'prompt' => trim((string) ($data['prompt'] ?? '')),
            'items' => $items,
            'correct_order' => $order,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function validatedSettings(array $settings): array
    {
        return [
            'practice_only' => (bool) ($settings['practice_only'] ?? false),
            'retakes_allowed' => (bool) ($settings['retakes_allowed'] ?? true),
            'retake_limit' => isset($settings['retake_limit']) && $settings['retake_limit'] !== '' ? (int) $settings['retake_limit'] : null,
            'show_correct_answer' => (bool) ($settings['show_correct_answer'] ?? false),
            'show_explanation' => (bool) ($settings['show_explanation'] ?? false),
            'teacher_review_required' => (bool) ($settings['teacher_review_required'] ?? false),
            'lock_next_lesson' => (bool) ($settings['lock_next_lesson'] ?? false),
            'normalize' => [
                'trim' => (bool) ($settings['normalize']['trim'] ?? true),
                'collapse_space' => (bool) ($settings['normalize']['collapse_space'] ?? true),
                'strip_punctuation' => (bool) ($settings['normalize']['strip_punctuation'] ?? false),
                'case_insensitive' => (bool) ($settings['normalize']['case_insensitive'] ?? true),
                'strict' => (bool) ($settings['normalize']['strict'] ?? false),
                'strip_tashkeel' => (bool) ($settings['normalize']['strip_tashkeel'] ?? false),
                'normalize_hamza' => (bool) ($settings['normalize']['normalize_hamza'] ?? false),
                'normalize_alef' => (bool) ($settings['normalize']['normalize_alef'] ?? false),
                'taa_marbuta' => (bool) ($settings['normalize']['taa_marbuta'] ?? false),
            ],
        ];
    }
}
