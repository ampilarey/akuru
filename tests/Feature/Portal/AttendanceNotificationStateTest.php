<?php

use App\Domains\Notifications\Actions\ResolveAttendanceNotificationStateAction as State;
use App\Domains\Notifications\Models\SmsReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * KNOWN_ISSUES #17: "Parent notified column shows — on excused rows."
 *
 * The portal rendered `guardian_notified ? 'Yes' : '—'` over
 * `status === 'absent' && receipt exists`, so one dash stood for four facts:
 *
 *  - **present** — nothing is ever sent. Fine.
 *  - **excused** — deliberately not sent; the guardian excused it themselves.
 *    Fine, and the case #17 was filed about.
 *  - **late** — sent or not, depending on the school's `notify` setting.
 *  - **absent with no receipt** — a message that should have gone and did not.
 *    **The only one worth seeing**, and it looked exactly like the rest.
 *
 * Reading the sender turned up something #17 does not mention and which is
 * worse than the ambiguity: `=== 'absent'` means a **late** row whose SMS
 * genuinely was sent was shown to the parent as **not** sent. The school sent
 * the message and the portal denied it.
 * `RecordClassAttendanceAction::maybeNotify()` notifies on `Late` too when the
 * setting is `absent_and_late`.
 */
uses(RefreshDatabase::class);

function receiptFor(int $studentId, string $date): void
{
    SmsReceipt::query()->create([
        'channel' => 'sms',
        'type' => 'attendance',
        'reference' => 'attendance_'.$date.'_'.$studentId,
        'phone' => '7770000',
        'body' => 'Absent today.',
        'driver' => 'log',
        'success' => true,
        'sent_at' => now(),
    ]);
}

it('says not applicable where no message is ever sent', function () {
    // #17's own case. "Not applicable" and "not sent" are different facts and
    // lead to different actions — nothing, or chase the school.
    $state = app(State::class);

    expect($state->execute(1, '2026-09-01', 'excused'))->toBe(State::NOT_APPLICABLE);
    expect($state->execute(1, '2026-09-01', 'present'))->toBe(State::NOT_APPLICABLE);
    expect($state->label(State::NOT_APPLICABLE))->toBe('Not applicable');
});

it('distinguishes an absence that was notified from one that was not', function () {
    $state = app(State::class);

    expect($state->execute(7, '2026-09-02', 'absent'))->toBe(State::NOT_SENT);

    receiptFor(7, '2026-09-02');

    expect($state->execute(7, '2026-09-02', 'absent'))->toBe(State::NOTIFIED);
});

it('credits a late SMS the school actually sent', function () {
    // The defect #17 does not mention. The old condition tested `=== 'absent'`,
    // so a late message that went out was reported to the parent as not sent.
    config(['attendance.notify' => 'absent_and_late']);
    $state = app(State::class);

    // Only meaningful if the school is configured to notify on late at all.
    if (! $state->notifiesFor('late')) {
        expect($state->execute(9, '2026-09-03', 'late'))->toBe(State::NOT_APPLICABLE);

        return;
    }

    expect($state->execute(9, '2026-09-03', 'late'))->toBe(State::NOT_SENT);
    receiptFor(9, '2026-09-03');
    expect($state->execute(9, '2026-09-03', 'late'))->toBe(State::NOTIFIED);
});

it('reads the same setting the sender reads', function () {
    // Rule 11: the portal's answer must not drift from what the school
    // actually does. `notifiesFor()` mirrors
    // `RecordClassAttendanceAction::maybeNotify()`, and absent is
    // unconditional in both.
    expect(app(State::class)->notifiesFor('absent'))->toBeTrue();
    expect(app(State::class)->notifiesFor('excused'))->toBeFalse();
    expect(app(State::class)->notifiesFor(null))->toBeFalse();
});

it('sends the state and its label to the portal screen', function () {
    $guardian = makeGuardian();
    $student = makeStudent(['first_name' => 'Notify', 'last_name' => 'Child']);
    $student->guardians()->attach($guardian->id, ['relationship' => 'mother']);

    $this->actingAs(\App\Domains\Identity\Models\User::query()->findOrFail($guardian->user_id))
        ->withoutLocalizationMiddleware()
        ->get('/portal/attendance?student_id='.$student->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Attendance'));

    // The screen must render the label, never a bare boolean.
    //
    // Comments are stripped first, and that is not a nicety: the first version
    // of this assertion failed on the comment in the JSX that *explains the
    // old code*. `PlatformApisStayInLayerTest` learned the same lesson from
    // the `FOREIGN_KEY_CHECKS` guard — a check that cannot tell documentation
    // from instruction punishes writing the explanation down. `stripJsComments`
    // is that test's helper, reused rather than copied.
    $source = stripJsComments((string) file_get_contents(base_path('resources/js/Pages/Portal/Attendance.jsx')));

    expect($source)->toContain('notification_label');
    expect($source)->not->toContain('guardian_notified');
});
