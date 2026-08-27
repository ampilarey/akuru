<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Actions\ListUserIdsWithPermissionAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use Illuminate\Support\Facades\Cache;

/**
 * S2 spec: "Daily digest option for admins."
 *
 * Off by default (ResolveNotificationSettingsAction). One in-app summary per
 * admin per day: today's absences plus registers still unfilled.
 */
class NotifyAdminDailyDigestAction
{
    public function execute(?int $academicYearId = null): int
    {
        if (! app(ResolveNotificationSettingsAction::class)->execute()['admin_daily_digest']) {
            return 0;
        }

        $admins = app(ListUserIdsWithPermissionAction::class)->execute('registers.manage');
        if ($admins === []) {
            return 0;
        }

        $today = now()->timezone(config('app.timezone'))->toDateString();

        $absent = ClassAttendance::query()
            ->whereDate('date', $today)
            ->whereIn('status', [AttendanceStatus::Absent->value, AttendanceStatus::Late->value])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->count();

        $unfilled = app(ListUnfilledRegistersAction::class)->execute($academicYearId)->count();
        $sent = 0;

        foreach ($admins as $userId) {
            $key = 'admin-digest:'.$userId.':'.$today;
            if (! Cache::add($key, true, now()->endOfDay())) {
                continue;
            }

            app(SendUserNotificationAction::class)->execute(
                (int) $userId,
                trans('notifications.digest.title', ['date' => $today]),
                trans('notifications.digest.body', ['absent' => $absent, 'unfilled' => $unfilled]),
                [
                    'category' => 'academics',
                    'date' => $today,
                    'absent_marks' => $absent,
                    'unfilled_registers' => $unfilled,
                ],
            );
            $sent++;
        }

        return $sent;
    }
}
