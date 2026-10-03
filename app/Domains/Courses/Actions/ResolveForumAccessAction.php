<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may use a course's forum (Moodle parity slice M3, STATUS §5oj).
 *
 * - **moderator**: whoever runs courses (`courses.manage`), and a teacher
 *   (`courses.review`) on the courses assigned to them (C16 slice N6). A
 *   moderator posts, pins, locks and hides.
 * - **participant**: a learner enrolled in the course, the same enrolments
 *   that open its lessons (active, approved, completed). A participant reads,
 *   starts topics and replies.
 * - Nobody else: the forum is the class's, not the public's.
 */
class ResolveForumAccessAction
{
    public const MODERATOR = 'moderator';

    public const PARTICIPANT = 'participant';

    public function execute(int $courseId, ?Authenticatable $user): ?string
    {
        if ($user === null) {
            return null;
        }
        if ($user->can('courses.manage')
            || ($user->can('courses.review') && in_array($courseId, app(ListCoursesTaughtByUserAction::class)->execute((int) $user->getAuthIdentifier()), true))) {
            return self::MODERATOR;
        }

        $student = app(ResolveStudentForUserAction::class)->execute((int) $user->getAuthIdentifier());
        if ($student !== null && CourseEnrollment::query()
            ->where('course_id', $courseId)
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->exists()) {
            return self::PARTICIPANT;
        }

        return null;
    }
}
