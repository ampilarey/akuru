<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\PickupPin;
use Illuminate\Support\Facades\Hash;

/**
 * Check a guardian's pick-up PIN.
 *
 * Returns false rather than throwing, so the caller decides what a failure
 * means — `RequestPickupAction` turns it into one message that never says
 * whether the PIN or the child was the problem.
 *
 * A guardian with no PIN set fails closed. There is no "no PIN means skip the
 * check" path, because that is exactly the shortcut that turns a second factor
 * into a suggestion — and it is the same fail-open shape as the BML webhook
 * (§5bp) that this session already had to fix once.
 */
class VerifyPickupPinAction
{
    public function execute(int $guardianUserId, string $pin): bool
    {
        $hash = PickupPin::query()->where('guardian_user_id', $guardianUserId)->value('pin_hash');

        if (! is_string($hash) || $hash === '') {
            return false;
        }

        return Hash::check(trim($pin), $hash);
    }
}
