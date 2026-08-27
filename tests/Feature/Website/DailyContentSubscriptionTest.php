<?php

use App\Domains\Identity\Actions\FindUserIdByVerifiedMobileAction;
use App\Domains\Identity\Actions\ReadVerifiedUserContactsAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\Website\Enums\DailySubscriptionChannel;
use App\Domains\Website\Enums\DailySubscriptionStatus;
use App\Domains\Website\Mail\DailyContentDigestMail;
use App\Domains\Website\Models\DailyContentDelivery;
use App\Domains\Website\Models\DailyContentSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function w24VerifiedUser(?string $phone = '+9607771001', ?string $email = null): User
{
    $user = User::factory()->create($email ? ['email' => $email] : []);
    if ($phone !== null) {
        UserContact::query()->create([
            'user_id' => $user->id,
            'type' => 'mobile',
            'value' => $phone,
            'is_primary' => true,
            'verified_at' => now(),
        ]);
    }
    if ($email !== null) {
        UserContact::query()->create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => $email,
            'is_primary' => true,
            'verified_at' => now(),
        ]);
    }

    return $user;
}

it('does not let a guest opt in', function () {
    $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.subscribe'))
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    expect(DailyContentSubscription::query()->count())->toBe(0);
});

it('lets a user opt in to SMS and forbids another user from pausing that row', function () {
    $owner = w24VerifiedUser('+9607771001');
    $other = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs($owner)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah', 'hadith'],
            'language' => 'en',
            'send_time' => '06:00',
        ])
        ->assertRedirect();

    $row = DailyContentSubscription::query()->sole();
    expect($row->user_id)->toBe($owner->id)
        ->and($row->channel)->toBe(DailySubscriptionChannel::Sms)
        ->and($row->status)->toBe(DailySubscriptionStatus::Active)
        ->and($row->content_types)->toBe(['ayah', 'hadith']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->post(route('public.daily.subscribe.pause', $row))
        ->assertForbidden();

    expect($row->fresh()->status)->toBe(DailySubscriptionStatus::Active);
});

it('does not auto-subscribe when a user is created', function () {
    User::factory()->create();

    expect(DailyContentSubscription::query()->count())->toBe(0);
});

it('delivers one combined SMS with permalinks and no Arabic, and makes no HTTP', function () {
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    w23PublishedAyah('2026-08-27');
    w23PublishedHadith('2026-08-27');
    $user = w24VerifiedUser('+9607771001');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah', 'hadith'],
            'language' => 'en',
            'send_time' => '06:00',
        ])
        ->assertRedirect();

    $sender = app(SmsSenderInterface::class);
    expect($sender)->toBeInstanceOf(LogSmsSender::class);

    $this->artisan('daily-content:deliver')->assertSuccessful();

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]['phone'])->toBe('+9607771001')
        ->and($sender->sent[0]['body'])->toContain('daily/ayah/2026-08-27')
        ->and($sender->sent[0]['body'])->toContain('daily/hadith/2026-08-27')
        ->and($sender->sent[0]['body'])->toContain('STOP')
        ->and($sender->sent[0]['body'])->not->toContain('بِسْمِ')
        ->and($sender->sent[0]['body'])->not->toContain('إنما')
        ->and(DailyContentDelivery::query()->count())->toBe(1);

    Http::assertNothingSent();

    $contacts = app(ReadVerifiedUserContactsAction::class)->execute($user->id);
    expect($contacts['phone'])->toBe('+9607771001');
});

it('does not send a second SMS on the same day', function () {
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    w23PublishedAyah('2026-08-27');
    $user = w24VerifiedUser('+9607771002');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    $sender = app(SmsSenderInterface::class);
    $this->artisan('daily-content:deliver')->assertSuccessful();
    $this->artisan('daily-content:deliver')->assertSuccessful();

    expect($sender->sent)->toHaveCount(1)
        ->and(DailyContentDelivery::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('sends nothing on an empty day and leaves no delivery row', function () {
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    $user = w24VerifiedUser('+9607771003');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    $sender = app(SmsSenderInterface::class);
    $this->artisan('daily-content:deliver')->assertSuccessful();

    expect($sender->sent)->toHaveCount(0)
        ->and(DailyContentDelivery::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('honors the unsubscribe token immediately so further delivers do not send', function () {
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    w23PublishedAyah('2026-08-27');
    $user = w24VerifiedUser('+9607771004');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    $row = DailyContentSubscription::query()->sole();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.unsubscribe', $row->unsubscribe_token))
        ->assertOk()
        ->assertSee('data-unsubscribed="1"', false);

    expect($row->fresh()->status)->toBe(DailySubscriptionStatus::Paused)
        ->and($row->fresh()->unsubscribe_reason?->value)->toBe('link');

    $sender = app(SmsSenderInterface::class);
    $this->artisan('daily-content:deliver')->assertSuccessful();

    expect($sender->sent)->toHaveCount(0);
    Http::assertNothingSent();
});

it('honors STOP on SMS immediately and logs the keyword unsubscribe', function () {
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    w23PublishedAyah('2026-08-27');
    $user = w24VerifiedUser('+9607771005');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    expect(app(FindUserIdByVerifiedMobileAction::class)->execute('7771005'))->toBe($user->id);

    $this->withoutLocalizationMiddleware()
        ->post(route('public.daily.sms-opt-out'), [
            'phone' => '7771005',
            'keyword' => 'STOP',
        ])
        ->assertNoContent();

    $row = DailyContentSubscription::query()->sole();
    expect($row->status)->toBe(DailySubscriptionStatus::Paused)
        ->and($row->unsubscribe_reason?->value)->toBe('keyword')
        ->and($row->unsubscribed_at)->not->toBeNull();

    $sender = app(SmsSenderInterface::class);
    $this->artisan('daily-content:deliver')->assertSuccessful();
    expect($sender->sent)->toHaveCount(0);
    Http::assertNothingSent();
});

it('delivers the email channel through Laravel Mail', function () {
    Mail::fake();
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    w23PublishedAyah('2026-08-27');
    $user = w24VerifiedUser(null, 'daily-reader@example.com');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'email',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    $this->artisan('daily-content:deliver')->assertSuccessful();

    Mail::assertSent(DailyContentDigestMail::class, function (DailyContentDigestMail $mail) {
        return $mail->hasTo('daily-reader@example.com')
            && str_contains($mail->subjectLine, '2026-08-27')
            && str_contains($mail->items[0]['url'] ?? '', 'daily/ayah/2026-08-27');
    });
    Http::assertNothingSent();
    expect(app(SmsSenderInterface::class))->toBeInstanceOf(LogSmsSender::class)
        ->and(app(SmsSenderInterface::class)->sent)->toHaveCount(0);
});

it('exports an admin CSV of subscribers', function () {
    $admin = actingPeopleAdmin(['daily_content.manage']);
    $user = w24VerifiedUser('+9607771006');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'sms',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.daily-subscriptions.index'))
        ->assertOk()
        ->assertSee('sms', false)
        ->assertSee('1 active', false);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.daily-subscriptions.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('sms')
        ->and($csv)->toContain('ayah')
        ->and($csv)->toContain('+9607771006');
});

it('ignores push at delivery time even when a push row exists', function () {
    Http::fake();
    $this->travelTo('2026-08-27 06:15:00');
    w23PublishedAyah('2026-08-27');
    $user = w24VerifiedUser('+9607771007');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('public.daily.subscribe.store'), [
            'channel' => 'push',
            'content_types' => ['ayah'],
            'language' => 'en',
        ])
        ->assertRedirect();

    expect(DailyContentSubscription::query()->sole()->channel)->toBe(DailySubscriptionChannel::Push);

    $this->artisan('daily-content:deliver')->assertSuccessful();

    expect(DailyContentDelivery::query()->count())->toBe(0)
        ->and(app(SmsSenderInterface::class)->sent)->toHaveCount(0);
    Http::assertNothingSent();
});

it('does not import Hifz, Courses models, Notifications models, or the SMS gateway from new W2.4 files', function () {
    $files = [
        app_path('Domains/Website/Actions/SaveDailyContentSubscriptionAction.php'),
        app_path('Domains/Website/Actions/UnsubscribeDailyContentSubscriptionAction.php'),
        app_path('Domains/Website/Actions/HonorDailyUnsubscribeKeywordAction.php'),
        app_path('Domains/Website/Actions/DeliverDailyContentSubscriptionsAction.php'),
        app_path('Domains/Website/Actions/ComposeDailySubscriptionMessageAction.php'),
        app_path('Domains/Website/Actions/ListDailyContentSubscriptionsAction.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/DailySubscriptionController.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/DailyUnsubscribeController.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/DailySubscriptionController.php'),
        app_path('Domains/Website/Console/DeliverDailyContentSubscriptionsCommand.php'),
        app_path('Domains/Website/Mail/DailyContentDigestMail.php'),
        app_path('Domains/Website/Models/DailyContentSubscription.php'),
        app_path('Domains/Website/Models/DailyContentDelivery.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)
            ->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toContain('App\\Domains\\Courses\\Models\\')
            ->and($src)->not->toContain('App\\Domains\\Notifications\\Models\\')
            ->and($src)->not->toContain('App\\Domains\\Identity\\Models\\')
            ->and($src)->not->toContain('SmsGatewayService')
            ->and($src)->not->toContain('ConsentType');
    }

    $deliver = file_get_contents(app_path('Domains/Website/Actions/DeliverDailyContentSubscriptionsAction.php'));
    expect($deliver)
        ->toContain('SmsSenderInterface')
        ->and($deliver)->toContain('ReadVerifiedUserContactsAction')
        ->and($deliver)->not->toContain('PushSenderInterface');

    expect(config('morph-map.daily_content_subscription'))->toBe(DailyContentSubscription::class)
        ->and(config('morph-map.daily_content_delivery'))->toBe(DailyContentDelivery::class);
});
