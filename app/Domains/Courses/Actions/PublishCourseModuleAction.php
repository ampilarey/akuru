<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §12 Module Management: **"Publish/unpublish modules depending on
 * permissions."**
 *
 * Nothing could change a module's status. `SaveCourseModuleAction` read
 * `$data['status'] ?? 'draft'` and no caller ever passed one, so every module
 * was permanently draft and the column was decoration.
 *
 * Publishing an empty module is refused. §12's own sequence — create, add
 * lessons, publish — puts content before publication, and a published module
 * with nothing in it is a heading students can see and open to find nothing.
 * That is a worse outcome than a refusal that says why.
 *
 * Unpublishing is deliberately **not** blocked by having lessons. Taking a
 * module back into draft is how an author fixes something students should not
 * be seeing, and a rule that forbade it exactly when the module has content
 * would forbid it exactly when it matters.
 */
class PublishCourseModuleAction
{
    public function execute(CourseModule $module, ModuleStatus $to, ?int $actorId = null): CourseModule
    {
        $from = $module->status instanceof ModuleStatus
            ? $module->status
            : (ModuleStatus::tryFrom((string) $module->status) ?? ModuleStatus::Draft);

        if ($from === $to) {
            // A retry or a double-submitted form is not an error (§11.4's
            // lesson, applied here).
            return $module;
        }

        if ($to === ModuleStatus::Published && ! $this->hasLessons($module)) {
            throw ValidationException::withMessages([
                'module' => 'Add a lesson before publishing this module — an empty published module is a heading students can open to find nothing.',
            ]);
        }

        $module->status = $to;
        $module->save();

        return $module->refresh();
    }

    private function hasLessons(CourseModule $module): bool
    {
        return Lesson::query()->where('course_module_id', $module->id)->exists();
    }
}
