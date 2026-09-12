<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\OtpAbuseEvent;
use Illuminate\Support\Facades\DB;

/**
 * SPEC §32: "OTP abuse event logging for admin review."
 *
 * The logging without the review screen would be a table nobody opens. What an
 * admin needs to answer is a single question — is this one confused parent
 * pressing Resend, or somebody burning the school's SMS credit — so the rows
 * are grouped by contact rather than listed flat. One person tripping the
 * cooldown four times in a minute is a support call; forty contacts tripping
 * the send ceiling in an hour is the cost abuse §32 names.
 */
class ListOtpAbuseEventsAction
{
    /** Grouping any wider than this stops being a pattern and starts being history. */
    private const WINDOW_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public function execute(int $days = self::WINDOW_DAYS): array
    {
        $days = max(1, min(90, $days));
        $since = now('Indian/Maldives')->subDays($days);

        $events = OtpAbuseEvent::query()
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        // Rule 3: a display name comes off the table, not out of
        // `Identity\Models\User` — and here the controller is in Identity
        // anyway, but the Action is the thing other domains could call.
        $userIds = $events->pluck('user_id')->filter()->unique()->all();
        $names = $userIds === [] ? collect() : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

        $groups = $events
            ->groupBy('contact_hash')
            ->map(function ($rows) use ($names): array {
                $first = $rows->first();
                $kinds = $rows->countBy('kind')->all();
                arsort($kinds);

                return [
                    // The hash is the group key, but it is not shown: it is a
                    // 64-character string that tells a human nothing. The tail
                    // is what lets somebody recognise their own number.
                    'key' => substr((string) $first->contact_hash, 0, 12),
                    'contact_tail' => $first->contact_tail,
                    'channel' => $first->channel,
                    'user' => $first->user_id ? ($names[$first->user_id] ?? '—') : null,
                    'trips' => $rows->count(),
                    'kinds' => $kinds,
                    'first_seen' => $rows->min('occurred_at')?->toDateTimeString(),
                    'last_seen' => $rows->max('occurred_at')?->toDateTimeString(),
                ];
            })
            ->sortByDesc('trips')
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'days' => $days,
            'total_trips' => $events->count(),
            // Shown rather than assumed: the numbers being enforced are
            // configurable per §32, so an admin reading this list needs to know
            // what the thresholds currently are.
            'limits' => [
                'max_sends' => (int) config('otp.max_sends', 3),
                'send_window_minutes' => (int) config('otp.send_window_minutes', 15),
                'resend_cooldown_seconds' => (int) config('otp.resend_cooldown_seconds', 60),
                'max_verify_attempts' => (int) config('otp.max_verify_attempts', 10),
                'retention_days' => (int) config('otp.abuse_log_retention_days', 90),
            ],
        ];
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    public function rows(int $days = self::WINDOW_DAYS): array
    {
        $data = $this->execute($days);
        $rows = [['Contact (last 4)', 'Code', 'Channel', 'Account', 'Trips', 'Kinds', 'First seen', 'Last seen']];

        foreach ($data['groups'] as $group) {
            $kinds = [];
            foreach ($group['kinds'] as $kind => $count) {
                $kinds[] = "{$kind} ×{$count}";
            }

            $rows[] = [
                // Deliberately still only the tail: an exported CSV is the
                // copy most likely to be mailed around. The code carries the
                // identity instead — two email addresses at the same provider
                // share their last four characters.
                '…'.($group['contact_tail'] ?? ''),
                $group['key'],
                $group['channel'],
                $group['user'] ?? '(no account yet)',
                $group['trips'],
                implode('; ', $kinds),
                $group['first_seen'],
                $group['last_seen'],
            ];
        }

        return $rows;
    }
}
