<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\PickupPin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A guardian sets or changes their pick-up PIN — step 2 of the protocol, and
 * the reason the plan says do not ship steps 1–5 without it.
 *
 * Hashed with the app's hasher, never stored or logged in the clear. Four to
 * eight digits: long enough not to be guessed in the handful of attempts a
 * person gets at a school gate, short enough to be remembered by somebody
 * holding a toddler in one hand.
 *
 * All-same and simple-run PINs are refused. "0000" and "1234" are the two
 * anybody would try first, and a second factor that the attacker guesses on
 * the first attempt is not a second factor.
 */
class SetPickupPinAction
{
    public function execute(int $guardianUserId, string $pin): PickupPin
    {
        $pin = trim($pin);

        if (! preg_match('/^\d{4,8}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => 'The PIN must be between 4 and 8 digits.',
            ]);
        }

        if ($this->tooObvious($pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Choose a less obvious PIN — not all one digit, and not a simple run.',
            ]);
        }

        return PickupPin::query()->updateOrCreate(
            ['guardian_user_id' => $guardianUserId],
            ['pin_hash' => Hash::make($pin)],
        );
    }

    private function tooObvious(string $pin): bool
    {
        if (preg_match('/^(\d)\1+$/', $pin)) {
            return true;
        }

        $ascending = true;
        $descending = true;
        for ($i = 1, $len = strlen($pin); $i < $len; $i++) {
            $step = (int) $pin[$i] - (int) $pin[$i - 1];
            $ascending = $ascending && $step === 1;
            $descending = $descending && $step === -1;
        }

        return $ascending || $descending;
    }
}
