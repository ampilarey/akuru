<?php

namespace App\Domains\Courses\Enums;

/**
 * SPEC §27's lesson completion rules — the ones that are implemented.
 *
 * §27 lists five:
 *
 *   > - Student clicks complete
 *   > - Required activities completed
 *   > - Quiz passed
 *   > - Assignment submitted
 *   > - Teacher approval received
 *
 * Two are here. **The other three are deliberately absent rather than
 * declared and ignored**, which is the same call the §26 slice made for
 * unlock modes: an enum case an admin can pick and the engine silently
 * ignores is worse than no case at all, because the screen then asserts a
 * rule that is not being enforced.
 *
 * What the missing three would need, recorded so the next slice starts from
 * facts rather than guesses:
 *
 *   - **Quiz passed** — attempts carry `score` and activities carry
 *     `passing_score`, so the data is there; the open question is what a
 *     lesson with several quizzes means, and whether a re-attempt after a
 *     pass can un-complete a lesson.
 *   - **Assignment submitted** — distinguishable from "completed" only for
 *     teacher-marked patterns, so it needs the pattern-4 activity list rather
 *     than the required-activity list used here.
 *   - **Teacher approval received** — `ReviewAttemptAction` exists, but
 *     approval today attaches to an *attempt*, not to a lesson, so this needs
 *     a decision about what approving a lesson means when it holds several
 *     reviewable pieces.
 */
enum LessonCompletionMode: string
{
    /**
     * §27's "Student clicks complete" — and, until this slice, the only
     * behaviour there was. Stays the default so no existing lesson changes.
     */
    case Click = 'click';

    /**
     * §27's "Required activities completed": every activity on the lesson
     * marked `is_required` has a submitted or scored attempt from this
     * enrolment.
     *
     * "Completed", not "passed" — §27 lists those as two separate rules, so a
     * submitted-but-wrong attempt satisfies this one. Passing is the rule
     * that is not built yet.
     */
    case RequiredActivities = 'required_activities';

    public function label(): string
    {
        return match ($this) {
            self::Click => 'Student clicks complete',
            self::RequiredActivities => 'Required activities completed',
        };
    }
}
