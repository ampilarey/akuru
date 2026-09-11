<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\PickupNotice;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

/**
 * Today's notices for one guardian — what they asked for and where it is up to.
 *
 * Separate from the staff console's list because the questions differ: the
 * office needs everybody's children, a parent needs only their own, and
 * filtering the staff view by guardian in a controller would be one `where`
 * away from showing a family somebody else's child.
 *
 * @return Collection<int, array<string, mixed>>
 */
class ListPickupNoticesForGuardianAction
{
    public function execute(int $guardianUserId, ?string $date = null): Collection
    {
        $date ??= now()->toDateString();

        $notices = PickupNotice::query()
            ->where('guardian_user_id', $guardianUserId)
            ->whereDate('date', $date)
            ->orderByDesc('requested_at')
            ->get();

        if ($notices->isEmpty()) {
            return collect();
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($notices->pluck('student_id')->all())
            ->keyBy('id');

        return $notices->map(fn (PickupNotice $notice): array => [
            'id' => (int) $notice->id,
            'student' => $students->get((int) $notice->student_id)['name'] ?? 'Unknown',
            'status' => $notice->status->value,
            'requested_at' => $notice->requested_at?->toDateTimeString(),
            'sent_at' => $notice->sent_at?->toDateTimeString(),
            'collected_at' => $notice->collected_at?->toDateTimeString(),
        ])->values();
    }
}
