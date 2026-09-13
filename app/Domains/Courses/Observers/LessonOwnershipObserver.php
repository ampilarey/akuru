<?php

namespace App\Domains\Courses\Observers;

use App\Domains\Courses\Actions\SyncContentBlockOwnershipAction;
use App\Domains\Courses\Models\Lesson;

/**
 * SPEC §14: "Use a model observer or service method to guarantee this."
 *
 * An observer rather than a call inside `SaveLessonAction`, deliberately. §14
 * asks for a **guarantee**, and a guarantee that lives in one Action is only
 * as good as every future caller remembering it. The observer catches every
 * write path — the Action, a console command, a seeder, a direct
 * `$lesson->save()` in a test — which is the difference between "the one place
 * we know about does it" and "it cannot not happen".
 *
 * It fires only when the lesson actually changed course or module, so an
 * ordinary title edit costs nothing.
 */
class LessonOwnershipObserver
{
    public function saved(Lesson $lesson): void
    {
        // `wasChanged` is false on create, and a freshly created lesson has no
        // blocks yet — blocks are created against a lesson that already
        // exists, and take their ids from it then.
        if (! $lesson->wasChanged('course_module_id') && ! $lesson->wasChanged('course_id')) {
            return;
        }

        app(SyncContentBlockOwnershipAction::class)->execute($lesson);
    }
}
