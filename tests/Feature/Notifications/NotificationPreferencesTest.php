<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Domains\Notifications\Actions\ResolveNotificationPreferencesAction;
use App\Domains\Notifications\Actions\SaveNotificationPreferencesAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use App\Domains\Notifications\Models\NotificationPreference;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * E22c — which categories reach me.
 *
 * With E22b's digest firing nightly to every family, the only control was the
 * global on/off switch. A parent who wants trip notices but not a nightly
 * summary had no way to say so, and the usual outcome is muting everything.
 */
it('opts everyone in by default', function () {
    $user = User::factory()->create();

    $prefs = app(ResolveNotificationPreferencesAction::class)->execute((int) $user->id);

    // Absence of a row means opted in — the inversion that fails where nobody
    // notices is treating "no record" as "no".
    expect($prefs)->not->toBeEmpty()
        ->and(collect($prefs)->every(fn (bool $on): bool => $on))->toBeTrue();
});

it('delivers a notification while nothing is opted out', function () {
    $user = User::factory()->create();

    app(SendUserNotificationAction::class)
        ->execute((int) $user->id, 'Parent evening', 'Thursday', ['category' => 'message']);

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $user->id))->toBe(1);
});

it('drops a notification in a category the person opted out of', function () {
    $user = User::factory()->create();
    app(SaveNotificationPreferencesAction::class)->execute((int) $user->id, ['digest' => false]);

    $result = app(SendUserNotificationAction::class)
        ->execute((int) $user->id, 'Tomorrow at school', 'Two lessons', ['category' => 'digest']);

    expect($result)->toBeNull()
        ->and(UserNotification::query()->count())->toBe(0);
});

it('muting the digest does not mute messages', function () {
    $user = User::factory()->create();
    app(SaveNotificationPreferencesAction::class)->execute((int) $user->id, ['digest' => false]);

    $sender = app(SendUserNotificationAction::class);
    $sender->execute((int) $user->id, 'Tomorrow at school', 'Two lessons', ['category' => 'digest']);
    $sender->execute((int) $user->id, 'Parent evening', 'Thursday', ['category' => 'message']);

    // This is the whole point of the slice: the nightly summary and a teacher
    // writing to you are different things and must be separately mutable.
    $titles = app(ListUserNotificationsAction::class)->execute((int) $user->id)->pluck('title')->all();

    expect($titles)->toBe(['Parent evening']);
});

it('one persons preference does not affect another', function () {
    $muted = User::factory()->create();
    $other = User::factory()->create();
    app(SaveNotificationPreferencesAction::class)->execute((int) $muted->id, ['message' => false]);

    $sender = app(SendUserNotificationAction::class);
    $sender->execute((int) $muted->id, 'Hello', 'Body', ['category' => 'message']);
    $sender->execute((int) $other->id, 'Hello', 'Body', ['category' => 'message']);

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $muted->id))->toBe(0)
        ->and(app(ListUserNotificationsAction::class)->unreadCount((int) $other->id))->toBe(1);
});

it('delivers a category that has no toggle', function () {
    $user = User::factory()->create();
    app(SaveNotificationPreferencesAction::class)->execute((int) $user->id, ['message' => false]);

    // 'course' is not selectable, so nobody has opted out of it. Silently
    // dropping it would make an un-configurable notification vanish.
    app(SendUserNotificationAction::class)
        ->execute((int) $user->id, 'Course update', 'Body', ['category' => 'course']);

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $user->id))->toBe(1);
});

it('ignores a hand-posted category that is not selectable', function () {
    $user = User::factory()->create();

    app(SaveNotificationPreferencesAction::class)->execute((int) $user->id, ['not_a_category' => false]);

    // Storing it would silently suppress a notification nobody can re-enable.
    expect(NotificationPreference::query()->where('category', 'not_a_category')->count())->toBe(0);
});

it('lets someone opt back in', function () {
    $user = User::factory()->create();
    $save = app(SaveNotificationPreferencesAction::class);

    $save->execute((int) $user->id, ['message' => false]);
    $save->execute((int) $user->id, ['message' => true]);

    app(SendUserNotificationAction::class)
        ->execute((int) $user->id, 'Hello', 'Body', ['category' => 'message']);

    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $user->id))->toBe(1)
        // Toggling twice updates the row rather than adding a second.
        ->and(NotificationPreference::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('stops message notifications reaching someone who muted them', function () {
    ['studentUser' => $studentUser, 'teacherUser' => $teacherUser] = seedFamilyAndTeacher();
    app(SaveNotificationPreferencesAction::class)->execute((int) $teacherUser->id, ['message' => false]);

    app(StartMessageThreadAction::class)->execute(
        (int) $studentUser->id,
        [(int) $teacherUser->id],
        'About Sunday',
        'Body',
    );

    // The message is still delivered to the inbox — muting the notification
    // must not lose the message itself.
    expect(app(ListUserNotificationsAction::class)->unreadCount((int) $teacherUser->id))->toBe(0)
        ->and(app(\App\Domains\Notifications\Actions\ListMessageInboxAction::class)
            ->unreadCount((int) $teacherUser->id))->toBe(1);
});
