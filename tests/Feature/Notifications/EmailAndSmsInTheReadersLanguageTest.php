<?php

use App\Domains\Identity\Models\User;
use App\Domains\Lending\Actions\NotifyLendingUserAction;
use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Mail\LibraryNoticeMail;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Support\Said;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
 * LA1 (ADR-044's second half, STATUS §5ug): the account keeps the language its
 * person last opened a page in, and an email or SMS to them is written in it —
 * not in the language of whoever, or whatever nightly job, sent it.
 */
uses(RefreshDatabase::class);

it('keeps the language a signed-in person last opened a page in', function () {
    $user = User::factory()->create();
    expect($user->locale)->toBeNull();

    foreach (['dv', 'ar'] as $locale) {
        app()->setLocale($locale);
        $this->withoutLocalizationMiddleware()->actingAs($user)->get('/portal/notifications')->assertOk();
        expect($user->fresh()->locale)->toBe($locale);
    }
    // A form sent does not change it; only a page opened does.
    app()->setLocale('en');
    $this->withoutLocalizationMiddleware()->actingAs($user)->post('/portal/notifications/read', []);
    expect($user->fresh()->locale)->toBe('ar');
});

it('writes an email to a reader in their own language, the mail itself too', function () {
    Mail::fake();
    $reader = User::factory()->create(['email' => 'reader@example.mv', 'locale' => 'dv']);
    app()->setLocale('en');

    app(NotifyLibraryUserAction::class)->withEmail($reader->id, Said::of('library_notices.continue_title'), Said::of('library_notices.continue_body', ['page' => 4, 'title' => 'Nahw']), '/library');

    Mail::assertQueued(LibraryNoticeMail::class, fn (LibraryNoticeMail $mail) => $mail->hasTo('reader@example.mv')
        && $mail->heading === __('library_notices.continue_title', [], 'dv')
        && $mail->body === __('library_notices.continue_body', ['page' => 4, 'title' => 'Nahw'], 'dv')
        && $mail->locale === 'dv');
});

it('writes an SMS to a reader in their own language, and in the sender\'s when the account keeps none', function () {
    $sent = [];
    app()->instance(SmsSenderInterface::class, new class($sent) implements SmsSenderInterface
    {
        public function __construct(private array &$sent) {}

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[$phoneNumber] = $message;

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp, int $minutes = 10): array
        {
            return ['success' => true];
        }
    });
    config(['lending.notices.sms' => true, 'lending.notices.email' => false]);
    app()->setLocale('en');
    $arabic = User::factory()->create(['phone' => '7001001', 'locale' => 'ar']);
    $none = User::factory()->create(['phone' => '7001002', 'locale' => null]);

    foreach ([$arabic, $none] as $user) {
        app(NotifyLendingUserAction::class)->execute($user->id, Said::of('lending.notice_returned_title'), Said::of('lending.notice_returned_body', ['title' => 'Nahw']), '/my-lending');
    }

    expect($sent['7001001'])->toContain(__('lending.notice_returned_title', [], 'ar'))
        ->and($sent['7001002'])->toContain(__('lending.notice_returned_title', [], 'en'));
});
