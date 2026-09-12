<?php

namespace App\Domains\Courses\Enums;

/**
 * SPEC §12 lists **Status** among a module's fields and
 * **"Publish/unpublish modules depending on permissions"** among what a course
 * creator must be able to do.
 *
 * `course_modules.status` existed as `varchar(20) NOT NULL DEFAULT 'draft'`
 * and **nothing anywhere ever changed it**. `SaveCourseModuleAction` read
 * `$data['status'] ?? 'draft'`, and no caller passed one — the controller
 * validated only `title` and `description`. So every module ever created was
 * permanently draft, the column was decoration, and the word "draft" in
 * `DeleteCourseModuleAction`'s refusal ("Only a draft module can be deleted")
 * described a condition nothing could ever fail.
 *
 * Two states, not more. §12 says "publish/unpublish", which is a switch; the
 * course itself carries the four-state workflow (§10.7) and a module inside a
 * published course does not need its own review cycle.
 */
enum ModuleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    /**
     * Whether students may see the module at all.
     *
     * The reason publishing has to mean something: a draft module is the
     * author's workspace, and §12's delete rule ("delete draft modules if
     * safe") only makes sense if publishing is what takes a module out of
     * that state.
     */
    public function isVisibleToStudents(): bool
    {
        return $this === self::Published;
    }
}
