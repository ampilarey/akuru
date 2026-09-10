<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Domains\Notifications\Actions\MarkUserNotificationsReadAction;
use App\Domains\Notifications\Actions\ReplyToMessageThreadAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Actions\StartClassMessageThreadAction;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * E22a — notifications reach a human, and messages emit them.
 *
 * Five features have been writing `user_notifications` for months — unfilled
 * registers, request decisions, substitute assignments, expiring HR documents,
 * the admin daily digest — and nobody could read a single one: `/notifications`
 * returns JSON that nothing calls, and the Blade view beside it was rendered by
 * no route at all.
 *
 * Messaging was the opposite problem: it delivered without announcing, so a
 * broadcast to thirty families told none of them.
 */
it('lists a users notifications newest first with an unread count', function () {
    $user = User::factory()->create();
    $sender = app(SendUserNotificationAction::class);

    $sender->execute((int) $user->id, 'Older', 'First message');
    $sender->execute((int) $user->id, 'Newer', 'Second message');

    $reader = app(ListUserNotificationsAction::class);

    expect($reader->execute((int) $user->id)->pluck('title')->all())->toBe(['Newer', 'Older'])
        ->and($reader->unreadCount((int) $user->id))->toBe(2);
});

it('does not leak one users notifications to another', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    app(SendUserNotificationAction::class)->execute((int) $theirs->id, 'Private', 'Not yours');

    expect(app(ListUserNotificationsAction::class)->execute((int) $mine->id))->toBeEmpty()
        ->and(app(ListUserNotificationsAction::class)->unreadCount((int) $mine->id))->toBe(0);
});

it('marks one notification read without touching the rest', function () {
    $user = User::factory()->create();
    $sender = app(SendUserNotificationAction::class);
    $first = $sender->execute((int) $user->id, 'One', 'a');
    $sender->execute((int) $user->id, 'Two', 'b');

    app(MarkUserNotificationsReadAction::class)->execute((int) $user->id, (int) $first->id);

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $user->id))->toBe(1);
});

it('marks everything read when no id is given', function () {
    $user = User::factory()->create();
    $sender = app(SendUserNotificationAction::class);
    $sender->execute((int) $user->id, 'One', 'a');
    $sender->execute((int) $user->id, 'Two', 'b');

    app(MarkUserNotificationsReadAction::class)->execute((int) $user->id);

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $user->id))->toBe(0);
});

it('cannot mark someone elses notification read with a hand-posted id', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $hers = app(SendUserNotificationAction::class)->execute((int) $theirs->id, 'Private', 'Not yours');

    // Scoped in the query, so the id simply matches nothing.
    $changed = app(MarkUserNotificationsReadAction::class)->execute((int) $mine->id, (int) $hers->id);

    expect($changed)->toBe(0)
        ->and(UserNotification::query()->find($hers->id)->read_at)->toBeNull();
});

it('tells every recipient of a new thread', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Aisha will miss the first period.',
    );

    $notice = app(ListUserNotificationsAction::class)->execute((int) $teacherUser->id)->first();

    expect($notice['title'])->toBe('About Sunday')
        ->and($notice['category'])->toBe('message')
        // A notification that points somewhere beats one that only announces.
        ->and($notice['href'])->toStartWith('/portal/messages/');
});

it('does not tell the author about their own message', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Body',
    );

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $studentUser->id))->toBe(0);
});

it('tells every family on a class broadcast', function () {
    ['teacherUser' => $teacherUser, 'class' => $class, 'guardians' => $guardians] =
        seedClassWithFamilies(3);

    app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Parent evening', 'Thursday at 7pm.');

    foreach ($guardians as $guardian) {
        expect(app(ListUserNotificationsAction::class)->unreadCount((int) $guardian->user_id))->toBe(1);
    }
});

it('never announces wider than the message was delivered', function () {
    ['teacherUser' => $teacherUser, 'class' => $class, 'guardians' => $guardians] =
        seedClassWithFamilies(6);

    $thread = app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Trip', 'Details');

    expect($thread->reply_policy)->toBe('author_only');

    app(ReplyToMessageThreadAction::class)
        ->execute((int) $thread->id, (int) $guardians[0]->user_id, 'Is transport included?');

    // Under author_only the reply reaches the teacher alone — and so must the
    // notification. Announcing to five other families would leak the reply.
    $reader = app(ListUserNotificationsAction::class);
    expect($reader->execute((int) $teacherUser->id)->first()['title'])->toBe('Trip');

    foreach (array_slice($guardians, 1) as $other) {
        expect($reader->execute((int) $other->user_id)->pluck('message')->all())
            ->not->toContain('Is transport included?');
    }
});

it('truncates a long message into the notification preview', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();

    app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'Long one',
        str_repeat('a', 500),
    );

    expect(mb_strlen(app(ListUserNotificationsAction::class)->execute((int) $teacherUser->id)->first()['message']))
        ->toBeLessThanOrEqual(160);
});
