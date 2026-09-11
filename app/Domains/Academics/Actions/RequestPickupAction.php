<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\PickupStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\PickupNotice;
use App\Domains\Academics\Models\PickupWindow;
use App\Domains\People\Actions\GuardianMayCollectStudentAction;
use App\Domains\People\Actions\VerifyPickupPinAction;
use Illuminate\Validation\ValidationException;

/**
 * Step 2 and 3: a guardian says "I am ten minutes away", and the office is told
 * to bring the child to reception.
 *
 * **Every gate is checked here rather than in the controller**, because this is
 * the one place a child gets released and a thin controller (rule 5) must not
 * be where the safety logic lives. In order:
 *
 *  1. pick-up must be open for today — staff control the window, not families;
 *  2. the guardian must be allowed to *collect* this child, which is a stricter
 *     question than being allowed to see them;
 *  3. the PIN must verify.
 *
 * The PIN is checked **last on purpose**. A wrong PIN is the only failure that
 * tells an attacker they got the earlier answers right, so the cheap structural
 * checks run first and a stranger never reaches the credential at all.
 *
 * Re-requesting for the same child on the same day returns the existing live
 * notice rather than making a second one. A parent who taps twice on a bad
 * connection should not appear at reception as two children.
 */
class RequestPickupAction
{
    public function execute(int $guardianUserId, int $studentId, string $pin, ?string $note = null): PickupNotice
    {
        $today = now()->toDateString();

        $open = PickupWindow::query()
            ->whereDate('date', $today)
            ->whereNull('closed_at')
            ->exists();

        if (! $open) {
            throw ValidationException::withMessages([
                'pickup' => 'Pick-up is not open at the moment. The school opens it for each day.',
            ]);
        }

        if (! app(GuardianMayCollectStudentAction::class)->execute($guardianUserId, $studentId)) {
            throw ValidationException::withMessages([
                'pickup' => 'You are not listed as someone who may collect this child.',
            ]);
        }

        if (! app(VerifyPickupPinAction::class)->execute($guardianUserId, $pin)) {
            throw ValidationException::withMessages([
                'pin' => 'That PIN is not right.',
            ]);
        }

        $existing = PickupNotice::query()
            ->where('student_id', $studentId)
            ->where('guardian_user_id', $guardianUserId)
            ->whereDate('date', $today)
            ->whereIn('status', [PickupStatus::Requested->value, PickupStatus::Sent->value])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $yearId = (int) AcademicYear::query()->where('status', 'active')->value('id');
        if ($yearId === 0) {
            throw ValidationException::withMessages([
                'pickup' => 'No academic year is active.',
            ]);
        }

        return PickupNotice::query()->create([
            'academic_year_id' => $yearId,
            'student_id' => $studentId,
            'guardian_user_id' => $guardianUserId,
            'date' => $today,
            'status' => PickupStatus::Requested->value,
            'requested_at' => now(),
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);
    }
}
