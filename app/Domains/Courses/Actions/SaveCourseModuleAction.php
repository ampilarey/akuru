<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Models\CourseModule;

class SaveCourseModuleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseModule $module = null): CourseModule
    {
        $payload = [
            'course_id' => (int) $data['course_id'],
            'title' => $data['title'],
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'description' => $data['description'] ?? null,
            // On create, append. On edit, **keep the position it has** — this
            // used to recompute `max(position) + 1` whenever no position was
            // passed, which was harmless while nothing could edit a module and
            // became a silent reorder the moment §12's edit path existed:
            // renaming a module sent it to the bottom of the course. The walk
            // caught it; no test would have, because every test that edits a
            // module passes a position.
            'position' => (int) ($data['position']
                ?? $module?->position
                ?? ((CourseModule::query()->where('course_id', $data['course_id'])->max('position') ?? -1) + 1)),
            // SPEC §12: publishing is its own transition
            // (PublishCourseModuleAction), so an ordinary edit must not carry
            // a status along with it — a rename that silently unpublished a
            // module would be the §11.4 mistake again.
            'status' => $module?->status ?? ModuleStatus::Draft,
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($module === null) {
            return CourseModule::query()->create($payload);
        }

        $module->fill($payload);
        $module->save();

        return $module->refresh();
    }
}
