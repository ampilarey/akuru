<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceMode;
use Illuminate\Support\Facades\DB;

class ResolveAttendanceSettingsAction
{
    /**
     * @return array{mode: AttendanceMode, notify: string, chronic_threshold: int, tardies_per_absence: int}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', [
                'attendance_mode',
                'attendance_notify',
                'attendance_chronic_threshold',
                'attendance_tardies_per_absence',
            ])
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
            // How many late marks the school counts as one absence. EduPage's
            // documented example is 3. **Zero means the rule is off**, which is
            // the default: a school that has not chosen a number must not have
            // one applied to its reported attendance behind its back.
            'tardies_per_absence' => max(0, (int) ($rows['attendance_tardies_per_absence'] ?? config('academics.attendance_tardies_per_absence', 0))),
        ];
    }
}
