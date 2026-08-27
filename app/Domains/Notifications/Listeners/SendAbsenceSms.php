<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class SendAbsenceSms
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handle(StudentMarkedAbsent $event): void
    {
        // Resolve recipients BEFORE consuming the once-per-day throttle key.
        // Claiming the key first meant a student with no guardian phone burned
        // the day's slot, so a guardian attached later that day could never be
        // notified (S2 audit).
        $phones = $this->parentPhones($event->studentId);
        if ($phones === []) {
            return;
        }

        $key = 'attendance-sms:'.$event->studentId.':'.$event->date;
        if (! Cache::add($key, true, now()->endOfDay())) {
            return;
        }

        $message = $this->message($event);
        $reference = 'attendance_'.$event->date.'_'.$event->studentId;

        foreach ($phones as $phone) {
            $result = $this->sms->sendSms($phone, $message, [
                'type' => 'attendance',
                'reference' => $reference,
            ]);

            if (($result['driver'] ?? null) === 'log') {
                continue;
            }

            app(RecordSmsReceiptAction::class)->execute([
                'channel' => 'sms',
                'type' => 'attendance',
                'reference' => $reference,
                'phone' => $phone,
                'body' => $message,
                'driver' => $result['driver'] ?? 'gateway',
                'success' => (bool) ($result['success'] ?? false),
            ]);
        }
    }

    /**
     * Trilingual per the S2 spec: rendered in the app locale from
     * resources/lang/{en,dv,ar}/notifications.php rather than a hardcoded
     * English string. There is no per-guardian locale column, so the app
     * locale is the best available signal.
     */
    private function message(StudentMarkedAbsent $event): string
    {
        $status = $event->status->value;
        $statusKey = 'notifications.attendance.status.'.$status;

        return trans('notifications.attendance.marked', [
            'name' => $event->studentName,
            // Any future AttendanceStatus without a translation falls back to
            // its raw value rather than printing the lookup key.
            'status' => Lang::has($statusKey) ? trans($statusKey) : $status,
            'date' => $event->date,
        ]);
    }

    /**
     * @return list<string>
     */
    private function parentPhones(int $studentId): array
    {
        return DB::table('guardian_student')
            ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
            ->where('guardian_student.student_id', $studentId)
            ->whereNotNull('parent_guardians.phone')
            ->pluck('parent_guardians.phone')
            ->map(fn ($phone) => trim((string) $phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
