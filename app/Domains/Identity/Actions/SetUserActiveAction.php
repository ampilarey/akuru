<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Whether a person can sign in (ADR-040 slice 4, BACKLOG C8).
 *
 * `users.is_active` is what every sign-in path checks — password, OTP,
 * account linking and switching — and what `DeleteUserAccountAction` sets
 * when an account with history is "removed". Until now nothing could set it
 * back: a deactivated account stayed deactivated unless somebody reached
 * the database. The actor never deactivates themselves, and the last active
 * system admin stays active.
 */
class SetUserActiveAction
{
    public function execute(User $user, bool $active, int $actorId): void
    {
        if (! $active) {
            if ((int) $user->id === $actorId) {
                throw ValidationException::withMessages(['active' => __('admin.access_error_self')]);
            }
            if ($user->hasRole('super_admin') && User::query()->role('super_admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist()) {
                throw ValidationException::withMessages(['active' => __('admin.access_error_last')]);
            }
        }

        $user->forceFill(['is_active' => $active])->save();
    }
}
