<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

/**
 * S2 notification policy toggles (spec "Notifications added in S2").
 *
 * All default OFF: a school should opt in before parents start receiving
 * behaviour messages, before admins get a daily digest, and before every
 * family gets a nightly summary.
 */
class ResolveNotificationSettingsAction
{
    /**
     * @return array{behavior_notify_parents: bool, admin_daily_digest: bool, family_daily_digest: bool}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['behavior_notify_parents', 'admin_daily_digest', 'family_daily_digest'])
            ->pluck('value', 'key');

        return [
            'behavior_notify_parents' => $this->flag($rows['behavior_notify_parents'] ?? null),
            'admin_daily_digest' => $this->flag($rows['admin_daily_digest'] ?? null),
            // Off by default for the same reason as the others: a school opts
            // in before every family starts receiving a nightly message.
            'family_daily_digest' => $this->flag($rows['family_daily_digest'] ?? null),
        ];
    }

    private function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }
}
