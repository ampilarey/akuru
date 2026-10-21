<?php

return [
    /*
     * KNOWN_ISSUES (2026-09-13, decided 2026-10-21, STATUS §5um): how a
     * leave's days are counted. 'working' (the default) leaves out the
     * weekend and the school calendar's holidays and closures, as the
     * Maldives' working week and the Employment Act count annual leave;
     * 'calendar' counts every day from the first to the last.
     */
    'leave_counts' => env('HR_LEAVE_COUNTS', 'working'),

    /* The weekend, as Carbon's day numbers (0 Sunday … 6 Saturday): Friday and Saturday. */
    'weekend_days' => [5, 6],
];
