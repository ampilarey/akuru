<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Academics\Events\BehaviorRecordLogged;
use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/**
 * S2 spec: "behavior incident with parent_visible (parent, optional setting)".
 *
 * Gating is done at dispatch (SaveBehaviorRecordAction) — the setting is off by
 * default, compliments never notify, and parent_visible=false never notifies.
 * This listener only decides how to phrase and send.
 */
class SendBehaviorParentSms
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handle(BehaviorRecordLogged $event): void
    {
        $phones = $this->parentPhones($event->studentId);
        if ($phones === []) {
            return;
        }

        $typeKey = 'notifications.behavior.type.'.$event->type;
        $message = trans('notifications.behavior.logged', [
            'name' => $event->studentName,
            'type' => Lang::has($typeKey) ? trans($typeKey) : $event->type,
            'date' => $event->date,
        ]);
        $reference = 'behavior_'.$event->recordId;

        foreach ($phones as $phone) {
            $result = $this->sms->sendSms($phone, $message, [
                'type' => 'behavior',
                'reference' => $reference,
            ]);

            if (($result['driver'] ?? null) === 'log') {
                continue;
            }

            app(RecordSmsReceiptAction::class)->execute([
                'channel' => 'sms',
                'type' => 'behavior',
                'reference' => $reference,
                'phone' => $phone,
                'body' => $message,
                'driver' => $result['driver'] ?? 'gateway',
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
