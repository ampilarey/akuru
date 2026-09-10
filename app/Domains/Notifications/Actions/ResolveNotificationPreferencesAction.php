<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\NotificationPreference;

/**
 * Which categories a person has chosen to receive.
 *
 * Absence of a row means **opted in**, so a category added later reaches
 * everyone by default rather than silently reaching nobody. That inversion —
 * treating "no record" as "no" — is the same trap E4's blank-audience rule had
 * to avoid, and it fails in the direction where nobody notices.
 */
class ResolveNotificationPreferencesAction
{
    /**
     * The categories a person can actually choose between.
     *
     * Deliberately a short list of what families and staff receive today, not
     * every string ever written to `user_notifications` — offering a toggle for
     * a category nobody sends is a setting that does nothing.
     */
    public const CATEGORIES = [
        'message' => 'Messages from staff',
        'digest' => 'Nightly summary of tomorrow',
        'academics' => 'Attendance and academics',
        'registers' => 'Register reminders',
        'hr' => 'Staff and HR',
        'finance' => 'Invoices and payments',
    ];

    /**
     * @return array<string, bool> every selectable category, defaulted to on
     */
    public function execute(int $userId): array
    {
        $saved = NotificationPreference::query()
            ->where('user_id', $userId)
            ->pluck('enabled', 'category');

        $out = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $out[$category] = $saved->has($category) ? (bool) $saved[$category] : true;
        }

        return $out;
    }

    /**
     * Whether one notification should be delivered.
     *
     * A category outside the selectable list is always delivered: it has no
     * toggle, so nobody has opted out of it, and silently dropping it would
     * make an un-configurable notification vanish.
     */
    public function allows(int $userId, ?string $category): bool
    {
        if ($category === null || ! array_key_exists($category, self::CATEGORIES)) {
            return true;
        }

        $row = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('category', $category)
            ->first();

        return $row === null || (bool) $row->enabled;
    }
}
