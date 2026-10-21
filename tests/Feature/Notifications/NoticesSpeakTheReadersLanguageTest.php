<?php

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Domains\Notifications\Models\UserNotification;
use App\Support\Said;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * HP3a (ADR-044, STATUS §5tx): a notice keeps its phrase, so the inbox says it
 * in the reader's language — not the sender's. The cart reminder went out from
 * the nightly job in English and read "Still thinking it over?" to a family
 * reading Dhivehi.
 */
it('says a phrase in any language, its values and joined sentences too, and stores and restores it', function () {
    $said = Said::of('shop.notice_complaint_body', ['number' => 'AK-1', 'kind' => Said::of('shop.complaint_kind_damaged')]);
    expect($said->in('dv'))->toBe(__('shop.notice_complaint_body', ['number' => 'AK-1', 'kind' => __('shop.complaint_kind_damaged', [], 'dv')], 'dv'))
        ->and((string) $said)->toBe($said->in('en'));

    $back = Said::fromArray(json_decode(json_encode($said), true));
    expect($back?->in('ar'))->toBe($said->in('ar'));

    $joined = Said::join([Said::of('shop.notice_paid_body', ['number' => 'C-1']), null, Said::of('shop.notice_paid_track', ['link' => 'https://x'])]);
    expect(Said::fromArray($joined->toArray())?->in('dv'))
        ->toBe(__('shop.notice_paid_body', ['number' => 'C-1'], 'dv').' '.__('shop.notice_paid_track', ['link' => 'https://x'], 'dv'));

    // A key that no longer exists, or no phrase at all: the caller keeps its text.
    expect(Said::fromArray(['key' => 'shop.no_such_phrase', 'params' => []]))->toBeNull()
        ->and(Said::fromArray(null))->toBeNull();
});

it('shows a notice sent in English to a family reading Dhivehi or Arabic in their own language', function () {
    $family = User::factory()->create();
    app()->setLocale('en');
    app(NotifyBookshopUserAction::class)->execute($family->id, Said::of('shop.notice_cart_reminder_title'), Said::of('shop.notice_cart_reminder_body', ['count' => 1, 'shops' => 'Fitrah']), '/shop/cart', 'cart_reminder');

    $row = UserNotification::query()->where('user_id', $family->id)->sole();
    // The row keeps the sender's words, for the push and for older readers.
    expect($row->title)->toBe(__('shop.notice_cart_reminder_title', [], 'en'));

    foreach (['dv', 'ar'] as $locale) {
        app()->setLocale($locale);
        $shown = app(ListUserNotificationsAction::class)->execute($family->id)->sole();
        expect($shown['title'])->toBe(__('shop.notice_cart_reminder_title', [], $locale))
            ->and($shown['message'])->toBe(__('shop.notice_cart_reminder_body', ['count' => 1, 'shops' => 'Fitrah'], $locale))
            ->and($shown['title'])->not->toBe($row->title);
    }

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($family)->get('/portal/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.title', __('shop.notice_cart_reminder_title', [], 'dv')));
});

it('shows a notice sent as plain words as it was written', function () {
    $family = User::factory()->create();
    UserNotification::query()->create(['user_id' => $family->id, 'type' => 'in_app', 'category' => 'shop', 'title' => 'Written before', 'message' => 'As it was', 'data' => [], 'status' => 'sent', 'sent_at' => now()]);

    app()->setLocale('dv');
    expect(app(ListUserNotificationsAction::class)->execute($family->id)->sole())->toMatchArray(['title' => 'Written before', 'message' => 'As it was']);
});

it('says a school request\'s decision with its state in the same language (HP3c)', function () {
    $said = Said::of('notifications.request.decision_title', ['status' => Said::of('notifications.request.status.approved')]);
    $back = Said::fromArray($said->toArray());

    expect($back?->in('dv'))->toBe(__('notifications.request.decision_title', ['status' => __('notifications.request.status.approved', [], 'dv')], 'dv'))
        ->and($back?->in('ar'))->not->toBe($back?->in('en'));
    foreach (['invoice.issued_title', 'invoice.reminder_body', 'hr.expiring_title', 'gift_card.redeemed_body'] as $key) {
        foreach (['dv', 'ar'] as $locale) {
            expect(__('notifications.'.$key, [], $locale))->not->toBe(__('notifications.'.$key, [], 'en'), "{$key} in {$locale}");
        }
    }
});
