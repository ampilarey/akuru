<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\Student;
use App\Domains\People\Models\StudentStatusHistory;

/**
 * A pupil's status history as the profile shows it (slice PE1).
 *
 * The reasons the system writes are stored as written — the directory's two
 * by `SaveStudentAction`, the promotion's two by Academics — so each row is
 * sent with the phrase its reason reads by, and a row from any day reads in
 * the page's language. A reason a person typed stays as typed.
 */
class ListStudentStatusHistoryAction
{
    private const SYSTEM_REASONS = [
        'Created via student directory' => 'history_reason_created',
        'Status changed via student directory' => 'history_reason_changed',
        'promotion: leave' => 'history_reason_promotion_leave',
        'promotion: graduate' => 'history_reason_promotion_graduate',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(Student $student): array
    {
        return $student->statusHistory->map(fn (StudentStatusHistory $row): array => [
            'id' => $row->id,
            'from_status' => $row->from_status?->value,
            'to_status' => $row->to_status?->value,
            'reason' => $row->reason,
            'reason_key' => self::SYSTEM_REASONS[(string) $row->reason] ?? null,
            'effective_date' => $row->effective_date?->toDateString(),
        ])->values()->all();
    }
}
