<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\AccountLinkEvent;
use App\Domains\Identity\Models\LinkedAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Break a link, in both directions.
 *
 * A link asserts "these two accounts are one person". Revoking it from one side
 * and leaving the other standing would leave an account that can still reach
 * back into one that has disowned it — which is the impersonation the
 * reciprocal design exists to prevent.
 *
 * Either party may unlink, and neither needs the other's password to do it:
 * withdrawing a claim must always be cheaper than making one.
 */
class UnlinkAccountAction
{
    public function execute(User $actor, int $linkedUserId, ?string $ip = null): void
    {
        $exists = LinkedAccount::query()
            ->where('user_id', $actor->id)
            ->where('linked_user_id', $linkedUserId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'account' => 'That account is not linked to yours.',
            ]);
        }

        DB::transaction(function () use ($actor, $linkedUserId, $ip): void {
            LinkedAccount::query()
                ->where(function ($query) use ($actor, $linkedUserId): void {
                    $query->where('user_id', $actor->id)->where('linked_user_id', $linkedUserId);
                })
                ->orWhere(function ($query) use ($actor, $linkedUserId): void {
                    $query->where('user_id', $linkedUserId)->where('linked_user_id', $actor->id);
                })
                ->delete();

            AccountLinkEvent::query()->create([
                'user_id' => (int) $actor->id,
                'target_user_id' => $linkedUserId,
                'action' => AccountLinkEvent::UNLINKED,
                'ip' => $ip,
            ]);
        });
    }
}
