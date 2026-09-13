<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Academics\Actions\ResolveAttendanceSettingsAction;

/**
 * KNOWN_ISSUES #17: "Parent notified column shows — on excused rows."
 *
 * The portal rendered `row.guardian_notified ? 'Yes' : '—'`, and the value
 * behind it was:
 *
 *     $row['guardian_notified'] = ($row['status'] ?? null) === 'absent'
 *         && $notified->execute(...);
 *
 * so a single `—` stood for four different facts, one of which is a problem
 * and three of which are not:
 *
 *  - **present** — nothing is ever sent. Fine.
 *  - **excused** — deliberately not sent; the guardian is the one who excused
 *    it. Fine, and the case #17 was filed about.
 *  - **late** — sent or not depending on the school's `notify` setting.
 *  - **absent with no receipt** — a message that should have gone and did not.
 *    **This is the one worth seeing**, and it looked exactly like the others.
 *
 * There was also a plain wrong answer hiding in there. `=== 'absent'` means a
 * **late** row whose SMS genuinely was sent still showed `—`: the school sent
 * the message, and the portal told the parent it had not. That is worse than
 * the ambiguity #17 describes, and it is what reading the sender turned up —
 * `RecordClassAttendanceAction::maybeNotify()` notifies on `Late` too when the
 * setting is `absent_and_late`.
 *
 * This resolves the three states properly, and reads the *same* setting the
 * sender reads, so the portal's answer cannot drift from what the school
 * actually does (rule 11).
 */
class ResolveAttendanceNotificationStateAction
{
    /** A message was sent and a receipt says it succeeded. */
    public const NOTIFIED = 'notified';

    /** A message should have gone for this status and no receipt says it did. */
    public const NOT_SENT = 'not_sent';

    /** No message is sent for this status. Not a failure. */
    public const NOT_APPLICABLE = 'not_applicable';

    public function __construct(private ResolveAttendanceSettingsAction $settings) {}

    public function execute(int $studentId, string $date, ?string $status): string
    {
        if (! $this->notifiesFor($status)) {
            return self::NOT_APPLICABLE;
        }

        return app(AbsenceWasNotifiedAction::class)->execute($studentId, $date)
            ? self::NOTIFIED
            : self::NOT_SENT;
    }

    /**
     * Mirrors `RecordClassAttendanceAction::maybeNotify()`. Absent always
     * notifies; late notifies only when the school asks for it; nothing else
     * ever does.
     */
    public function notifiesFor(?string $status): bool
    {
        return match ($status) {
            'absent' => true,
            'late' => ($this->settings->execute()['notify'] ?? null) === 'absent_and_late',
            default => false,
        };
    }

    /**
     * What a parent should read. The three states say different things and
     * lead to different actions — chase the school, or nothing at all.
     */
    public function label(string $state): string
    {
        return match ($state) {
            self::NOTIFIED => 'Sent',
            self::NOT_SENT => 'Not sent',
            default => 'Not applicable',
        };
    }
}
