<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\StaffStatus;
use App\Domains\People\Models\StaffProfile;
use App\Domains\People\Models\Teacher;

/**
 * Keeps `teachers.status` in step with the staff profile that owns it.
 *
 * ## The two records
 *
 * A member of staff is recorded twice: `staff_profiles`, which the staff
 * directory edits and which carries `active` / `on_leave` / `ended`, and
 * `teachers`, the older row the timetable, the registers and every teacher
 * picker key off, which carries `active` / `inactive` / `terminated`.
 *
 * Nothing connected them. `teachers.status` was written **once**, at creation,
 * always as `'active'` — `TeacherController::store` and `EnsureTeacherRowAction`
 * both hardcode it and no path anywhere updates it. So ending somebody's
 * employment in the staff directory left their teacher row `active` for ever,
 * and every screen that filters on it went on offering them.
 *
 * That also meant four `where('status', 'active')` filters — the meeting-slot
 * picker, the teacher-contact list, `ListActiveTeachersAction` and the staff
 * counter — could never exclude anybody. They were correct code guarding a
 * column that never moved.
 *
 * ## The mapping, and what it deliberately leaves alone
 *
 * Only `ended` deactivates. **`on_leave` does not**, and that is asserted in
 * the tests rather than assumed: a teacher on leave is exactly the one a
 * school needs to keep finding in a picker, because that is how the cover for
 * them gets arranged.
 *
 * Restoring is symmetric, so ending employment is not a one-way trap: a
 * profile that leaves `ended` puts its teacher row back to `active`. It only
 * ever touches a row it previously set to `terminated`, so a status set some
 * other way in future is not clobbered.
 */
class SyncTeacherRowStatusAction
{
    public function execute(StaffProfile $profile): void
    {
        $teacher = Teacher::query()
            ->where(function ($query) use ($profile): void {
                $query->where('staff_profile_id', $profile->id);

                // In practice the link is `user_id`: nothing sets
                // `teachers.staff_profile_id`, though the column exists and is
                // read by ResolveTeacherIdForStaffProfileAction. Matching on
                // either means this works now and keeps working if the newer
                // column starts being populated.
                if ($profile->user_id !== null) {
                    $query->orWhere('user_id', $profile->user_id);
                }
            })
            ->first();

        if ($teacher === null) {
            return;
        }

        $ended = $profile->status === StaffStatus::Ended;

        if ($ended && $teacher->status !== 'terminated') {
            $teacher->forceFill(['status' => 'terminated'])->save();

            return;
        }

        if (! $ended && $teacher->status === 'terminated') {
            $teacher->forceFill(['status' => 'active'])->save();
        }
    }
}
