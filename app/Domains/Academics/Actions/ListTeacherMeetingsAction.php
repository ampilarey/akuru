<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Models\MeetingSlot;
use Illuminate\Support\Collection;

/**
 * The meetings a teacher has been booked for — their own, and nobody else's.
 *
 * ## What was missing
 *
 * `meeting_slots.teacher_id` names the teacher, and the family is shown that
 * name on the slot before they book it. So both ends of a parent-teacher
 * meeting knew whose meeting it was, and **the teacher had nowhere to see
 * one**: `/academics/meetings` is gated on `meetings.manage`, which `admin`,
 * `headmaster`, `supervisor` and `super_admin` hold and `teacher` does not,
 * and neither `/teach/schedule` (course sessions) nor `/portal/teacher`
 * mentioned meetings at all. Walked and confirmed before it was built —
 * STATUS §5ec.
 *
 * ## Why this is a reader and not a permission
 *
 * The review-queue gap found the same afternoon (KNOWN_ISSUES #28) could not be
 * closed the same way, because `course_instructor` has no rows and no writer,
 * so "my courses" cannot be expressed. Here `teacher_id` is on the slot and
 * populated, so **"my meetings" is a `where` clause** — no permission is
 * widened, nothing school-wide is disclosed, and a teacher seeing who booked
 * their own slot needs nobody's decision.
 *
 * Read-only on purpose. Generating, publishing and cancelling slots stay with
 * the office on `/academics/meetings`; this answers "who is coming to see me,
 * and when", which is SPEC §36's *view session schedules* for the one kind of
 * session the teacher surfaces had no answer for.
 */
class ListTeacherMeetingsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $teacherId, ?string $from = null): Collection
    {
        // Today, not now: a meeting at 18:00 should still be on the teacher's
        // screen at 18:05, when they are looking for the name of the parent
        // sitting down in front of them.
        $from ??= now()->toDateString();

        $slots = MeetingSlot::query()
            ->with(['bookings'])
            ->where('teacher_id', $teacherId)
            // Drafts are the office's working copy; a teacher seeing one would
            // be told about a meeting that may never be offered.
            ->where('status', MeetingSlotStatus::Published)
            ->whereDate('date', '>=', $from)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return app(ListMeetingSlotsAction::class)->serialize($slots);
    }
}
