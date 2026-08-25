<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceMode;
use Illuminate\Support\Facades\DB;

class ResolveAttendanceSettingsAction
{
    /**
     * @return array{mode: AttendanceMode, notify: string, chronic_threshold: int}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['attendance_mode', 'attendance_notify', 'attendance_chronic_threshold'])
            ->pluck('value', 'key');

        $mode = AttendanceMode::tryFrom((string) ($rows['attendance_mode'] ?? config('academics.attendance_mode', 'per_lesson')))
            ?? AttendanceMode::PerLesson;

        $notify = (string) ($rows['attendance_notify'] ?? config('academics.attendance_notify', 'absent_only'));
        if (! in_array($notify, ['absent_only', 'absent_and_late'], true)) {
            $notify = 'absent_only';
        }

        return [
            'mode' => $mode,
            'notify' => $notify,
            'chronic_threshold' => max(1, (int) ($rows['attendance_chronic_threshold'] ?? config('academics.attendance_chronic_threshold', 5))),
        ];
    }
}
