<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendAbsenceSms
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handle(StudentMarkedAbsent $event): void
    {
        $key = 'attendance-sms:'.$event->studentId.':'.$event->date;
        if (! Cache::add($key, true, now()->endOfDay())) {
            return;
        }

        $phones = $this->parentPhones($event->studentId);
        if ($phones === []) {
            return;
        }

        $status = $event->status->value;
        $message = "Akuru Institute: {$event->studentName} was marked {$status} on {$event->date}.";
        $reference = 'attendance_'.$event->date.'_'.$event->studentId;

        foreach ($phones as $phone) {
            $result = $this->sms->sendSms($phone, $message, [
                'type' => 'attendance',
                'reference' => $reference,
            ]);

            app(RecordSmsReceiptAction::class)->execute([
                'type' => 'attendance',
                'reference' => $reference,
                'phone' => $phone,
                'driver' => $result['driver'] ?? (array_key_exists('message_id', $result) ? 'gateway' : 'log'),
                'success' => (bool) ($result['success'] ?? false),
            ]);
        }
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
