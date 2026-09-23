<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Which `teachers` row stands for this staff profile — the row the timetable,
 * the cover register and the absence list key off.
 *
 * `teachers.staff_profile_id` exists (S1.4) and is the link this used to read
 * exclusively. Nothing populates it: `EnsureTeacherRowAction` and the staff
 * account creator key the two records on `user_id`, and the only place the
 * column was ever set was the leave test, by hand. So approving a member of
 * staff's leave recorded the attendance and moved the balance, and then found
 * no teacher — no absence on the cover register, no cover request for the
 * lessons — on every deployment, silently (the first HR walk, STATUS §5fd).
 *
 * Read either link, the way `SyncTeacherRowStatusAction` already does: the
 * explicit column when somebody sets it, the shared user otherwise.
 */
class ResolveTeacherIdForStaffProfileAction
{
    public function execute(?int $staffProfileId): ?int
    {
        if ($staffProfileId === null) {
            return null;
        }

        $id = DB::table('teachers')->where('staff_profile_id', $staffProfileId)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        $userId = DB::table('staff_profiles')->where('id', $staffProfileId)->value('user_id');
        if ($userId === null) {
            return null;
        }

        $id = DB::table('teachers')->where('user_id', $userId)->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
