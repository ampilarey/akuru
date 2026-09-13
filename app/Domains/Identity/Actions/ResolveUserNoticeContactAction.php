<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;

/**
 * Where to reach a person with a transactional notice, and what to call them.
 *
 * Deliberately **not** `ReadVerifiedUserContactsAction`, which returns verified
 * contacts only. That is the right rule for anything that proves identity, and
 * the wrong one here: a family registering for a course for the first time has
 * not verified anything yet, and a confirmation that skips them is the failure
 * this whole thread of work has been about.
 *
 * So the order is the one the registration flow has always used — the account's
 * own column first, then a contact row of that type, verified or not.
 *
 * It exists so that other domains can ask "how do I reach this user?" without
 * importing `Identity\Models\User`, which is rule 3's boundary. Who a user is
 * and where their contacts live is Identity's business; everyone else takes
 * three strings.
 */
class ResolveUserNoticeContactAction
{
    /**
     * @return array{name: string, email: ?string, mobile: ?string}
     */
    public function execute(int $userId): array
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return ['name' => 'Parent/Guardian', 'email' => null, 'mobile' => null];
        }

        return [
            'name' => (string) ($user->name ?? 'Parent/Guardian'),
            'email' => $user->email
                ?? $user->contacts()->where('type', 'email')->value('value'),
            'mobile' => $user->mobile
                ?? $user->contacts()->where('type', 'mobile')->value('value'),
        ];
    }
}
