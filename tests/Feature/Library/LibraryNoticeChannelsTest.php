<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Mail\LibraryNoticeMail;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Notifications\Actions\SaveNotificationPreferencesAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * STATUS §5lq, the Library's notices by email and SMS: off until the office
 * turns them on; then the decisions and the money go by email and SMS too,
 * never the reader nudges, and never to someone who switched library
 * notices off. In-app, always.
 */
function libraryNoticeSms(): object
{
    $sms = new class implements SmsSenderInterface
    {
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = [$phoneNumber, $message, $options['reference'] ?? null];

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return ['success' => true, 'driver' => 'log'];
        }
    };
    app()->instance(SmsSenderInterface::class, $sms);

    return $sms;
}

function libraryApplicant(string $email, ?string $phone = '7771234'): User
{
    $user = User::factory()->create(['email' => $email, 'phone' => $phone]);
    WriterApplication::query()->create(['user_id' => $user->id, 'display_name' => 'Notice Writer', 'agreement_accepted_at' => now()]);

    return $user;
}

function decideApplicationOf(User $user): void
{
    app(DecideWriterApplicationAction::class)->execute(WriterApplication::query()->where('user_id', $user->id)->value('id'), User::factory()->create()->id, true);
}

function libraryNoticeSwitches(User $admin, bool $email, bool $sms)
{
    return test()->withoutLocalizationMiddleware()->actingAs($admin)->put(route('admin.library.settings.update'), [
        'refund_window_days' => 7, 'default_writer_commission' => 70, 'min_payout' => 100, 'gift_card_min' => 50, 'gift_card_max' => 5000,
        'gift_card_expiry_months' => 0, 'research_reviews_required' => 1, 'payouts_enabled' => false,
        'notices_email' => $email, 'notices_sms' => $sms,
    ]);
}

it('keeps every notice in the app until the office turns email and SMS on', function () {
    Mail::fake();
    $sms = libraryNoticeSms();
    $writer = libraryApplicant('writer-one@example.test');

    decideApplicationOf($writer);

    expect(UserNotification::query()->where('user_id', $writer->id)->count())->toBe(1);
    Mail::assertNothingQueued();
    expect($sms->sent)->toBe([]);
});

it('sends the decisions by email and SMS once the office allows, and shows the switches on the settings screen', function () {
    Mail::fake();
    $sms = libraryNoticeSms();
    $admin = actingSystemAdmin(['library.manage']);
    libraryNoticeSwitches($admin, true, true)->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.library.settings'))
        ->assertInertia(fn ($page) => $page->where('settings.notices_email.value', true)->where('settings.notices_sms.value', true)->where('settings.notices_email.default', false));
    auth()->logout();

    $writer = libraryApplicant('writer-two@example.test');
    decideApplicationOf($writer);

    Mail::assertQueued(LibraryNoticeMail::class, fn ($m) => $m->hasTo('writer-two@example.test') && $m->heading === 'You are now an Akuru writer' && str_ends_with((string) $m->link, '/write'));
    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0][0])->toBe('7771234')
        ->and($sms->sent[0][1])->toStartWith('Akuru Library: You are now an Akuru writer.')
        ->and($sms->sent[0][2])->toBe('library_writer_application_decided');

    // A reader nudge (no event) stays in the app.
    app(NotifyLibraryUserAction::class)->execute($writer->id, 'Keep reading', 'You left a book half read.', '/library');
    Mail::assertQueuedCount(1);
    expect($sms->sent)->toHaveCount(1);
});

it('sends by email only when only email is on, and nothing to someone who switched library notices off', function () {
    Mail::fake();
    $sms = libraryNoticeSms();
    libraryNoticeSwitches(actingSystemAdmin(['library.manage']), true, false)->assertSessionHasNoErrors();
    auth()->logout();

    $writer = libraryApplicant('writer-three@example.test', null);
    decideApplicationOf($writer);
    Mail::assertQueued(LibraryNoticeMail::class, fn ($m) => $m->hasTo('writer-three@example.test'));
    expect($sms->sent)->toBe([]);

    $quiet = libraryApplicant('quiet@example.test');
    app(SaveNotificationPreferencesAction::class)->execute($quiet->id, ['library' => false]);
    decideApplicationOf($quiet);
    Mail::assertNotQueued(LibraryNoticeMail::class, fn ($m) => $m->hasTo('quiet@example.test'));
});
