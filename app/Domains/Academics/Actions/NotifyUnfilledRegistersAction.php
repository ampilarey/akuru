<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Notifications\Actions\SendUserNotificationAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * S2 spec: "unfilled-register reminder (teacher, end of day)".
 *
 * In-app rather than SMS — this is a nudge to staff who are already in the
 * system, not a message to a parent's phone.
 */
class NotifyUnfilledRegistersAction
{
    public function execute(?int $academicYearId = null): int
    {
        $rows = app(ListUnfilledRegistersAction::class)->execute($academicYearId);
        if ($rows->isEmpty()) {
            return 0;
        }

        $byTeacher = $rows->groupBy(fn (array $row) => (int) ($row['teacher_id'] ?? 0));
        $today = now()->timezone(config('app.timezone'))->toDateString();
        $sent = 0;

        foreach ($byTeacher as $teacherId => $registers) {
            $teacherId = (int) $teacherId;
            if ($teacherId < 1) {
                continue;
            }

            // Resolve the recipient BEFORE claiming the once-per-day key, so a
            // teacher with no linked user account cannot burn the day's slot
            // (same ordering bug fixed in SendAbsenceSms, #122).
            $userId = (int) DB::table('teachers')->where('id', $teacherId)->value('user_id');
            if ($userId < 1) {
                continue;
            }

            $key = 'unfilled-registers:'.$teacherId.':'.$today;
            if (! Cache::add($key, true, now()->endOfDay())) {
                continue;
            }

            $count = $registers->count();
            app(SendUserNotificationAction::class)->execute(
                $userId,
                trans('notifications.registers.unfilled_title'),
                trans('notifications.registers.unfilled_body', ['count' => $count]),
                [
                    'category' => 'academics',
                    'unfilled_count' => $count,
                    'date' => $today,
                ],
            );
            $sent++;
        }

        return $sent;
    }
}
