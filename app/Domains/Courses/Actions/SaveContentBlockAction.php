<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\Lesson;

class SaveContentBlockAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?ContentBlock $block = null): ContentBlock
    {
        $lesson = Lesson::query()->findOrFail((int) $data['lesson_id']);
        $validated = app(ValidateContentBlockDataAction::class)->execute(
            (string) $data['type'],
            is_array($data['data'] ?? null) ? $data['data'] : [],
            is_array($data['settings'] ?? null) ? $data['settings'] : [],
        );

        $payload = [
            'lesson_id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'course_module_id' => $lesson->course_module_id,
            'type' => $data['type'],
            'position' => (int) ($data['position'] ?? (($lesson->blocks()->max('position') ?? -1) + 1)),
            'title' => $data['title'] ?? null,
            'data' => $validated['data'],
            'settings' => $validated['settings'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($block === null) {
            return ContentBlock::query()->create($payload);
        }

        $block->fill($payload);
        $block->save();

        return $block->refresh();
    }
}
