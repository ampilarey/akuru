<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;

/**
 * The mobile numbers of the people who should be told operationally important
 * things — the admins.
 *
 * It exists because two other domains were answering this question themselves
 * by importing `Identity\Models\User` and calling Spatie's `role()` scope
 * directly, which is rule 3's boundary: "a domain must never directly query or
 * import another domain's Eloquent models". Who counts as an admin, and where
 * their number is kept (a typed contact row, or the legacy `phone` column), is
 * Identity's business and nobody else's.
 *
 * Returns plain strings, so callers need nothing of Identity's but this.
 */
class ListAdminMobileNumbersAction
{
    /**
     * @return list<string>
     */
    public function execute(): array
    {
        $numbers = [];

        foreach (User::role(['super_admin', 'admin'])->get() as $admin) {
            // The typed contact row first, then the legacy column — the same
            // order the callers this replaces used, so nobody's number stops
            // working on the way across.
            $mobile = $admin->contacts()->where('type', 'mobile')->value('value')
                ?? $admin->phone
                ?? null;

            if ($mobile) {
                $numbers[] = (string) $mobile;
            }
        }

        return array_values(array_unique($numbers));
    }
}
