<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\PickupPin;

/**
 * Whether a guardian has set a pick-up PIN yet.
 *
 * Exists so the portal can say "set a PIN before you can ask for your child"
 * without reading People's model (rule 3). It answers *whether*, never *what* —
 * there is no action anywhere that hands the PIN back out.
 */
class GuardianHasPickupPinAction
{
    public function execute(int $guardianUserId): bool
    {
        return PickupPin::query()->where('guardian_user_id', $guardianUserId)->exists();
    }
}
