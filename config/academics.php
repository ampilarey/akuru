<?php

return [
    'register_lock_days' => (int) env('REGISTER_LOCK_DAYS', 7),
    'attendance_mode' => env('ATTENDANCE_MODE', 'per_lesson'),
    'attendance_notify' => env('ATTENDANCE_NOTIFY', 'absent_only'),
    'attendance_chronic_threshold' => (int) env('ATTENDANCE_CHRONIC_THRESHOLD', 5),
];
