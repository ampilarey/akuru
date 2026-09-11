<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceMode;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Validation\ValidationException;

/**
 * The school's attendance policy, set by the school.
 *
 * `ResolveAttendanceSettingsAction` has read these four keys since August and
 * **nothing has ever written them**: `SettingsController` has only `index` and
 * `clearCache`, so changing the chronic threshold or the tardy rule meant
 * editing the `settings` table by hand. Policy that can only be changed by a
 * DBA is policy the school does not really own.
 *
 * Every value is validated here rather than trusted from the form, because
 * these numbers move reported attendance for every pupil at once.
 */
class SaveAttendanceSettingsAction
{
    public function execute(array $data): void
    {
        $mode = AttendanceMode::tryFrom((string) ($data['mode'] ?? ''));

        if ($mode === null) {
            throw ValidationException::withMessages(['mode' => 'Choose how the school takes attendance.']);
        }

        $notify = (string) ($data['notify'] ?? 'absent_only');

        if (! in_array($notify, ['absent_only', 'absent_and_late'], true)) {
            throw ValidationException::withMessages(['notify' => 'Choose what families are told about.']);
        }

        $chronic = (int) ($data['chronic_threshold'] ?? 5);

        if ($chronic < 1 || $chronic > 100) {
            throw ValidationException::withMessages([
                'chronic_threshold' => 'A chronic-absence threshold of fewer than one day is not a threshold.',
            ]);
        }

        $tardies = (int) ($data['tardies_per_absence'] ?? 0);
        $minutes = (int) ($data['part_lesson_minutes'] ?? 0);

        // Zero is the off switch for both, and must stay reachable: a school
        // that turns a rule off is asking for its plain numbers back.
        if ($tardies < 0 || $tardies > 20) {
            throw ValidationException::withMessages(['tardies_per_absence' => 'Between 0 (off) and 20.']);
        }

        if ($minutes < 0 || $minutes > 240) {
            throw ValidationException::withMessages(['part_lesson_minutes' => 'Between 0 (off) and 240 minutes.']);
        }

        $set = app(SetSettingAction::class);

        $set->execute('attendance_mode', $mode->value, 'string', 'academics', 'Attendance mode');
        $set->execute('attendance_notify', $notify, 'string', 'academics', 'Attendance notifications');
        $set->execute('attendance_chronic_threshold', $chronic, 'integer', 'academics', 'Chronic absence threshold (days)');
        $set->execute('attendance_tardies_per_absence', $tardies, 'integer', 'academics', 'Late marks counted as one absence');
        $set->execute('attendance_part_lesson_minutes', $minutes, 'integer', 'academics', 'Minutes late before a lesson stops counting as attended');
    }
}
