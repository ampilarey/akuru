<?php

namespace App\Domains\Academics\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
    case LeftEarly = 'left_early';

    /**
     * The statuses a person marking a register or a daily grid may choose.
     *
     * `Excused` is not one of them. S2_SPEC §S2.4 says where an excusal comes
     * from: approving an `absence_note` with `affects_attendance=true` flips
     * the matching absent rows to excused **and links the note**. An excusal
     * is the record of a guardian's explanation having been accepted, not a
     * fourth button on the grid (KNOWN_ISSUES #15).
     *
     * This is the list the screens render; the rule itself is enforced in
     * `RecordClassAttendanceAction`, which every writer passes through — a
     * button that is merely absent from a page is not a guard.
     *
     * @return list<self>
     */
    public static function teacherSettable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status) => $status !== self::Excused,
        ));
    }
}
