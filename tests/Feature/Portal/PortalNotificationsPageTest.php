<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * E22a walked over HTTP: a person opens the notification centre, sees what was
 * written for them, and marks it read. Before this there was no page at all —
 * only a JSON route nothing called and a Blade view no route rendered.
 */
it('shows a person their notifications and marks one read', function () {
    $user = User::factory()->create();
    $notice = app(SendUserNotificationAction::class)
        ->execute((int) $user->id, 'Register unfilled', 'Period 2 is still empty.');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get('/portal/notifications')
        ->assertOk()
        ->assertSee('Register unfilled');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post('/portal/notifications/read', ['id' => $notice->id])
        ->assertRedirect('/portal/notifications');

    expect(UserNotification::query()->find($notice->id)->read_at)->not->toBeNull();
});

it('marks everything read in one go', function () {
    $user = User::factory()->create();
    $sender = app(SendUserNotificationAction::class);
    $sender->execute((int) $user->id, 'One', 'a');
    $sender->execute((int) $user->id, 'Two', 'b');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post('/portal/notifications/read', [])
        ->assertRedirect();

    expect(UserNotification::query()->whereNull('read_at')->count())->toBe(0);
});

it('shares an unread count with every inertia page', function () {
    $user = User::factory()->create();
    app(SendUserNotificationAction::class)->execute((int) $user->id, 'One', 'a');

    // The count is what makes notifications discoverable at all — before this
    // they existed only in a table.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get('/portal/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', 1));
});

it('shows an honest empty state', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get('/portal/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Notifications')
            ->where('notifications', [])
            ->where('auth.unread_notifications', 0)
        );
});

it('requires a login', function () {
    $this->withoutLocalizationMiddleware()
        ->get('/portal/notifications')
        ->assertRedirect();
});
