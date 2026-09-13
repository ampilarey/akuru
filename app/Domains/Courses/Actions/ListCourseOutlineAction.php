<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;

class ListCourseOutlineAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $courseId): array
    {
        $course = Course::query()->findOrFail($courseId);
        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->with(['lessons.blocks', 'lessons.currentRevision', 'lessons.glossaryItems'])
            ->orderBy('position')
            ->get();

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'workflow_status' => $course->workflow_status?->value ?? $course->workflow_status,
            ],
            'glossaryItems' => app(ListGlossaryItemsAction::class)->execute()->values(),
            // SPEC §26 "Pass quiz first" names *which* assessment, so the
            // control needs the course's published ones.
            'assessments' => app(ListPublishedAssessmentsAction::class)->execute()
                ->filter(fn (array $row): bool => (int) ($row['course_id'] ?? 0) === (int) $course->id)
                ->values()
                ->all(),
            'modules' => $modules->map(fn (CourseModule $module) => [
                'id' => $module->id,
                'title' => $module->title,
                // SPEC §12 lists Description and Status among a module's
                // fields. Neither reached the screen, so neither could be
                // checked or changed — the status especially, since nothing
                // could write it either.
                'description' => $module->description,
                'status' => $module->status?->value ?? $module->status,
                'position' => $module->position,
                'lessons' => $module->lessons->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'slug' => $lesson->slug,
                    'status' => $lesson->status?->value ?? $lesson->status,
                    'current_revision_id' => $lesson->current_revision_id,
                    'is_preview' => (bool) $lesson->is_preview,
                    // SPEC §13 lists "Completion rule" as a lesson field, and
                    // a rule that is stored but never shown cannot be checked.
                    'completion_rule' => app(EvaluateLessonCompletionAction::class)->mode($lesson)->value,
                    // SPEC §26's lesson-level unlock rule (§13's "Unlock
                    // rule"). Shown so an author can see which lessons are
                    // gated and on what.
                    'unlock_rule' => is_array($lesson->unlock_rule) ? $lesson->unlock_rule : null,
                    'revision_number' => $lesson->currentRevision?->revision_number,
                    'glossary' => $lesson->glossaryItems->map(fn ($item) => $item->toPayload(
                        (int) $item->pivot->position,
                        (bool) $item->pivot->is_required,
                    ))->values(),
                    'blocks' => $lesson->blocks->map(fn ($block) => [
                        'id' => $block->id,
                        'type' => $block->type,
                        'position' => $block->position,
                        'title' => $block->title,
                        'data' => $block->data,
                        'settings' => $block->settings,
                        // SPEC §28.1 / §16: required-or-optional is part of
                        // what an author sets and what the snapshot carries.
                        'is_required' => (bool) $block->is_required,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
