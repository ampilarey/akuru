<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\LessonStatus;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonRevision;
use Illuminate\Support\Facades\DB;

class PublishLessonAction
{
    public function execute(Lesson $lesson, ?int $publishedBy = null): LessonRevision
    {
        return DB::transaction(function () use ($lesson, $publishedBy): LessonRevision {
            $lesson->load('blocks');
            $next = (int) $lesson->revisions()->max('revision_number') + 1;
            $snapshot = [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'revision_number' => $next,
                'blocks' => $lesson->blocks->map(fn ($block) => [
                    'id' => $block->id,
                    'type' => $block->type,
                    'position' => $block->position,
                    'title' => $block->title,
                    'data' => $block->data,
                    'settings' => $block->settings,
                ])->values()->all(),
            ];

            $revision = LessonRevision::query()->create([
                'lesson_id' => $lesson->id,
                'revision_number' => $next,
                'snapshot_json' => $snapshot,
                'published_by' => $publishedBy,
                'published_at' => now(),
            ]);

            $lesson->status = LessonStatus::Published;
            $lesson->current_revision_id = $revision->id;
            $lesson->published_at = $revision->published_at;
            $lesson->save();

            return $revision;
        });
    }
}
