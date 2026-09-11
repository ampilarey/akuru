<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\AccountLinkEvent;
use App\Domains\Identity\Models\LinkedAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Swap the session to a linked account.
 *
 * **This is a real login, not impersonation.** Both accounts were proved by the
 * same person, so the target's own roles and permissions apply in full — and
 * nothing of the previous identity survives the swap. That is what keeps the E6
 * rule intact without a single line of special-casing: somebody who switches
 * into a pupil account holds the pupil's roles, so they cannot confirm anything
 * as a guardian, because a pupil never could.
 *
 * Three things happen deliberately on the way through:
 *
 *  - **The session id is rotated**, because changing who a session belongs to
 *    while keeping its id is session fixation. `Auth::login()` does this itself
 *    via `SessionGuard::updateSession()` → `migrate(true)`. An explicit
 *    `regenerate()` was written here first and then removed: it was dead code,
 *    proved so by deleting it and watching the rotation test still pass. The
 *    test stays, and now guards the guard.
 *  - **Password confirmation is cleared.** Confirming your password as a
 *    teacher must not carry into the other identity — the plan asks for re-auth
 *    on sensitive actions, and forgetting the timestamp is how you get it.
 *  - **The link is re-read from the database**, never from the request. The
 *    only thing the caller supplies is which account, and an account that is
 *    not verifiably linked to the signed-in user is refused.
 */
class SwitchAccountAction
{
    public function execute(Request $request, User $actor, int $targetUserId): User
    {
        $linked = LinkedAccount::query()
            ->verified()
            ->where('user_id', $actor->id)
            ->where('linked_user_id', $targetUserId)
            ->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'account' => 'That account is not linked to yours.',
            ]);
        }

        $target = User::query()->find($targetUserId);

        if ($target === null || ! $target->is_active) {
            throw ValidationException::withMessages([
                'account' => 'That account cannot be used at the moment.',
            ]);
        }

        AccountLinkEvent::query()->create([
            'user_id' => (int) $actor->id,
            'target_user_id' => (int) $target->id,
            'action' => AccountLinkEvent::SWITCHED,
            'ip' => $request->ip(),
        ]);

        // Rotates the session id as part of logging in — see the class comment.
        Auth::login($target);

        // After the login, so the migrated session does not carry it over.
        $request->session()->forget('auth.password_confirmed_at');

        $target->forceFill(['last_login_at' => now()])->save();

        return $target;
    }
}
