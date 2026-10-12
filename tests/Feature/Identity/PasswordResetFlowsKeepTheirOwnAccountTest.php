<?php

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Notifications\OtpEmailNotification;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * STATUS §5rc: a reset code resets only the account it was sent for.
 *
 * The two reset flows — the forgot-password pages and the pages the login
 * links to — kept their progress in loose session keys, and shared one of
 * them. "Verified" was a flag of its own, not tied to the contact proved,
 * so:
 * - on either flow, a code proved for one's own number, then a new request
 *   for someone else's, left the flag set on their contact, and the new
 *   password went to their account;
 * - the forgot-password flow's account (found by an ID card number) stayed
 *   in the session while the other flow swapped the contact under it.
 *
 * Each flow now keeps one record, replaced whole by every request, with the
 * contact whose code was proved read back against the contact it names.
 */
function resetSms(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{0: string, 1: string}> */
        public array $codes = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            return ['success' => true];
        }

        public function sendOtp(string $phoneNumber, string $otp, int $minutes = 10): array
        {
            $this->codes[] = [$phoneNumber, $otp];

            return ['success' => true];
        }

        public function codeFor(string $phoneNumber): ?string
        {
            foreach (array_reverse($this->codes) as [$to, $code]) {
                if ($to === $phoneNumber) {
                    return $code;
                }
            }

            return null;
        }
    };
    app()->instance(SmsSenderInterface::class, $fake);

    return $fake;
}

/** An account with a verified mobile and the password `old-password`. */
function resetAccount(string $mobile, array $attributes = []): User
{
    $user = User::factory()->create($attributes + ['password' => Hash::make('old-password')]);
    UserContact::create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+960'.$mobile, 'is_primary' => true, 'verified_at' => now()]);

    return $user;
}

function keepsItsPassword(User $user): bool
{
    return Hash::check('old-password', $user->fresh()->password);
}

const NEW_PASSWORD = ['password' => 'Brand-new-pass-2026', 'password_confirmation' => 'Brand-new-pass-2026'];

it('does not reset the account an ID card number named with a code sent to someone else', function () {
    $sms = resetSms();
    $victim = resetAccount('7000001', ['national_id' => 'A900001']);
    $attacker = resetAccount('7000002');
    $web = $this->withoutLocalizationMiddleware();

    // The victim's ID card number on the forgot-password page: their code goes to them.
    $web->post('/forgot-password', ['identifier' => 'A900001'])->assertRedirect(route('password.reset.verify'));
    // One's own number on the other reset pages: that code comes to oneself.
    $web->post('/password/otp/send', ['contact' => '7000002'])->assertRedirect(route('password.otp.verify.form'));

    // One's own code on the forgot-password page's code step, then a new password.
    $web->post('/reset-password/verify', ['code' => $sms->codeFor('+9607000002')]);
    $web->post('/reset-password', NEW_PASSWORD)->assertRedirect(route('password.request'));

    expect(keepsItsPassword($victim))->toBeTrue()
        ->and(keepsItsPassword($attacker))->toBeTrue();
});

it('asks the forgot-password page for a new code when it is asked again for another account', function () {
    $sms = resetSms();
    $victim = resetAccount('7000011');
    $attacker = resetAccount('7000012');
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/forgot-password', ['identifier' => '7000012']);
    $web->post('/reset-password/verify', ['code' => $sms->codeFor('+9607000012')])->assertRedirect(route('password.reset'));

    // Asked again, for someone else's number, after one's own code was proved.
    $web->post('/forgot-password', ['identifier' => '7000011']);
    $web->get('/reset-password')->assertRedirect(route('password.request'));
    $web->post('/reset-password', NEW_PASSWORD)->assertRedirect(route('password.request'));

    expect(keepsItsPassword($victim))->toBeTrue()
        ->and(keepsItsPassword($attacker))->toBeTrue();
});

it('asks the reset pages the login links to for a new code when they are asked again for another number', function () {
    $sms = resetSms();
    $victim = resetAccount('7000021');
    $attacker = resetAccount('7000022');
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/password/otp/send', ['contact' => '7000022']);
    $web->post('/password/otp/verify', ['code' => $sms->codeFor('+9607000022')])->assertRedirect(route('password.otp.reset.form'));

    // Asked again, for someone else's number, after one's own code was proved.
    $web->post('/password/otp/send', ['contact' => '7000021']);
    $web->get('/password/otp/reset')->assertRedirect(route('password.otp.request'));
    $web->post('/password/otp/reset', NEW_PASSWORD)->assertRedirect(route('password.otp.request'));

    expect(keepsItsPassword($victim))->toBeTrue()
        ->and(keepsItsPassword($attacker))->toBeTrue();
});

it('does not let the forgot-password page swap the number under a code the other pages proved', function () {
    $sms = resetSms();
    $victim = resetAccount('7000031');
    resetAccount('7000032');
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/password/otp/send', ['contact' => '7000032']);
    $web->post('/password/otp/verify', ['code' => $sms->codeFor('+9607000032')]);
    $web->post('/forgot-password', ['identifier' => '7000031']);
    $web->post('/password/otp/reset', NEW_PASSWORD);

    expect(keepsItsPassword($victim))->toBeTrue();
});

it('still resets a password by phone on the pages the login links to', function () {
    $sms = resetSms();
    $user = resetAccount('7000041');
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/password/otp/send', ['contact' => '7000041']);
    $web->get('/password/otp/verify')->assertOk()->assertSee('+9607000041');
    $web->post('/password/otp/verify', ['code' => $sms->codeFor('+9607000041')]);
    $web->get('/password/otp/reset')->assertOk();
    $web->post('/password/otp/reset', NEW_PASSWORD)->assertRedirect(route('login'));

    expect(Hash::check('Brand-new-pass-2026', $user->fresh()->password))->toBeTrue()
        ->and(session('password_otp_reset'))->toBeNull();
});

it('still resets a password by email on the forgot-password page', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    UserContact::create(['user_id' => $user->id, 'type' => 'email', 'value' => 'reader@example.com', 'is_primary' => true, 'verified_at' => now()]);
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/forgot-password', ['identifier' => 'reader@example.com']);
    $code = null;
    Notification::assertSentOnDemand(OtpEmailNotification::class, function (OtpEmailNotification $notice) use (&$code) {
        $code = $notice->otp;

        return true;
    });
    $web->post('/reset-password/verify', ['code' => $code])->assertRedirect(route('password.reset'));
    $web->get('/reset-password')->assertOk();
    $web->post('/reset-password', NEW_PASSWORD)->assertRedirect(route('login'));

    expect(Hash::check('Brand-new-pass-2026', $user->fresh()->password))->toBeTrue()
        ->and(session('password_reset'))->toBeNull();
});

it("still resets a child's password with the code sent to the parent's phone", function () {
    $sms = resetSms();
    $parent = resetAccount('7000051');
    $child = resetAccount('7000052', ['national_id' => 'A900051']);
    $child->contacts()->delete(); // a child signs in with the ID card number, and has no contact of its own
    $student = Student::query()->create(['user_id' => $child->id, 'first_name' => 'Aisha', 'last_name' => 'Ali', 'date_of_birth' => '2014-03-01', 'gender' => 'female']);
    $guardian = ParentGuardian::query()->create(['user_id' => $parent->id, 'first_name' => 'Hassan', 'last_name' => 'Ali', 'phone' => '7000051', 'email' => 'parent-reset@example.com', 'address' => 'Malé', 'relationship' => 'father']);
    $student->guardians()->attach($guardian->id, ['relationship' => 'father']);
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/forgot-password', ['identifier' => 'A900051']);
    $web->post('/reset-password/verify', ['code' => $sms->codeFor('+9607000051')])->assertRedirect(route('password.reset'));
    $web->post('/reset-password', NEW_PASSWORD)->assertRedirect(route('login'));

    expect(Hash::check('Brand-new-pass-2026', $child->fresh()->password))->toBeTrue()
        ->and(keepsItsPassword($parent))->toBeTrue();
});

it('sends no reset code to a contact nobody verified, on the pages the sign-in links to', function () {
    // Slice SI1: an email typed at registration is stored unverified, and could
    // be anyone's; whoever held it could reset the account.
    $sms = resetSms();
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    UserContact::create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607000061', 'is_primary' => false, 'verified_at' => null]);
    $web = $this->withoutLocalizationMiddleware();

    $web->post('/password/otp/send', ['contact' => '7000061'])->assertRedirect(route('password.otp.verify.form'));

    expect($sms->codeFor('+9607000061'))->toBeNull()
        ->and(session('password_otp_reset.contact_id'))->toBeNull();
    $web->post('/password/otp/verify', ['code' => '123456'])->assertRedirect(route('password.otp.request'));
    expect(keepsItsPassword($user))->toBeTrue();
});
