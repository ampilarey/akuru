<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\PickupStatus;
use App\Domains\Academics\Models\PickupNotice;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The staff console: *Waiting for departure* and *Left*.
 *
 * Scoped to one date and nothing else, which is what the plan means by
 * "auto-expiring daily" — yesterday's list is not stale data to clear up, it
 * is simply a different day's query. No cleanup job, no expiry column.
 */
class ListPickupNoticesAction
{
    /**
     * @return array{waiting: Collection<int, array<string, mixed>>, left: Collection<int, array<string, mixed>>}
     */
    public function execute(?string $date = null): array
    {
        $date ??= now()->toDateString();

        $notices = PickupNotice::query()
            ->whereDate('date', $date)
            ->orderBy('requested_at')
            ->get();

        if ($notices->isEmpty()) {
            return ['waiting' => collect(), 'left' => collect()];
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($notices->pluck('student_id')->all())
            ->keyBy('id');

        $guardians = DB::table('users')
            ->whereIn('id', $notices->pluck('guardian_user_id')->all())
            ->pluck('name', 'id');

        $rows = $notices->map(fn (PickupNotice $notice): array => [
            'id' => (int) $notice->id,
            'student_id' => (int) $notice->student_id,
            'student' => $students->get((int) $notice->student_id)['name'] ?? 'Unknown',
            'student_number' => $students->get((int) $notice->student_id)['student_number'] ?? null,
            'guardian' => $guardians->get((int) $notice->guardian_user_id) ?? 'Unknown',
            'status' => $notice->status->value,
            'note' => $notice->note,
            'requested_at' => $notice->requested_at?->toDateTimeString(),
            'sent_at' => $notice->sent_at?->toDateTimeString(),
            'collected_at' => $notice->collected_at?->toDateTimeString(),
        ]);

        return [
            // Requested and sent are both "still here" from the office's point
            // of view — a child sent to reception has not gone yet.
            'waiting' => $rows->whereIn('status', [PickupStatus::Requested->value, PickupStatus::Sent->value])->values(),
            'left' => $rows->where('status', PickupStatus::Collected->value)->values(),
        ];
    }
}
