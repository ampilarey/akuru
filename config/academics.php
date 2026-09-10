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
];
