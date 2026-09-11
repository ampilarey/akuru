<?php

return [
    'register_lock_days' => (int) env('REGISTER_LOCK_DAYS', 7),
    'attendance_mode' => env('ATTENDANCE_MODE', 'per_lesson'),
    'attendance_notify' => env('ATTENDANCE_NOTIFY', 'absent_only'),
    'attendance_chronic_threshold' => (int) env('ATTENDANCE_CHRONIC_THRESHOLD', 5),

    // How many late marks add up to one absence. 0 disables the roll-up.
    // ResolveAttendanceSettingsAction reads this as the fallback behind the
    // per-school DB row, exactly as it does for the three settings above; it
    // was the only one of the four with no config entry to fall back to.
    'attendance_tardies_per_absence' => (int) env('ATTENDANCE_TARDIES_PER_ABSENCE', 0),

    // E10d — minutes late before a lesson stops counting as attended. Zero is
    // off, and is the default: without a number chosen by the school, a late
    // arrival counts as attended however late, which is the behaviour that
    // shipped before this setting existed.
    'attendance_part_lesson_minutes' => (int) env('ATTENDANCE_PART_LESSON_MINUTES', 0),
];
