<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\PickupStatus;
use App\Domains\Academics\Models\PickupNotice;
use Illuminate\Validation\ValidationException;

/**
 * Steps 4 and 5: staff send the child out, the guardian confirms they have
 * them, and the loop closes.
 *
 * One action for both transitions because they are one state machine, and
 * splitting them would let a caller reach `collected` without passing through
 * `sent` — which, on paper, is a child marked as collected who never left the
 * classroom.
 *
 * Each transition is guarded by the state it must come from. Re-sending or
 * re-confirming is refused rather than silently ignored: two members of staff
 * both believing they released a child is precisely the confusion this module
 * exists to prevent.
 */
class AdvancePickupNoticeAction
{
    /** Staff: the child has been sent to reception. */
    public function send(PickupNotice $notice, int $staffUserId): PickupNotice
    {
        $this->require($notice, PickupStatus::Requested, 'This notice is not waiting to be sent.');

        $notice->update([
            'status' => PickupStatus::Sent->value,
            'sent_at' => now(),
            'sent_by' => $staffUserId,
        ]);

        return $notice->refresh();
    }

    /**
     * Guardian: "I have the child", reached by id.
     *
     * The portal lives in another domain and may not name `PickupNotice`
     * (rule 3), so the id crosses the boundary and the lookup happens here.
     * A missing notice is refused with the same sentence as a wrong one — the
     * family screen should not become a way to probe which notices exist.
     */
    public function collectById(int $noticeId, int $guardianUserId): void
    {
        $notice = PickupNotice::query()->find($noticeId);

        if ($notice === null) {
            throw ValidationException::withMessages([
                'pickup' => 'This pick-up was requested by somebody else.',
            ]);
        }

        $this->collect($notice, $guardianUserId);
    }

    /** Guardian: "I have the child." */
    public function collect(PickupNotice $notice, int $guardianUserId): PickupNotice
    {
        $this->require($notice, PickupStatus::Sent, 'The school has not sent this child out yet.');

        // Only the guardian who asked may close their own loop.
        if ((int) $notice->guardian_user_id !== $guardianUserId) {
            throw ValidationException::withMessages([
                'pickup' => 'This pick-up was requested by somebody else.',
            ]);
        }

        $notice->update([
            'status' => PickupStatus::Collected->value,
            'collected_at' => now(),
        ]);

        return $notice->refresh();
    }

    /** Either side changed their mind, before the child moved. */
    public function cancel(PickupNotice $notice): PickupNotice
    {
        $this->require($notice, PickupStatus::Requested, 'A child already sent out cannot be cancelled.');

        $notice->update([
            'status' => PickupStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        return $notice->refresh();
    }

    private function require(PickupNotice $notice, PickupStatus $expected, string $message): void
    {
        if ($notice->status !== $expected) {
            throw ValidationException::withMessages(['pickup' => $message]);
        }
    }
}
