<?php

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P1: a customer signs in on the phone number, the Bake
 * & Grill way — the password if the account has one, otherwise a code by
 * SMS; after a code the account sets a password before it buys anything,
 * and from then on the number takes the password only.
 */
function phoneSms(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{0: string, 1: string}> */
        public array $codes = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            return ['success' => true];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            $this->codes[] = [$phoneNumber, $otp];

            return ['success' => true];
        }

        public function lastCode(): string
        {
            return (string) end($this->codes)[1];
        }
    };
    app()->instance(SmsSenderInterface::class, $fake);
    RateLimiter::clear('new_reg_otp_send:'.md5('mobile+9607771111'));

    return $fake;
}

function phoneWeb()
{
    return test()->withoutLocalizationMiddleware();
}

it('sends a code to a new number, makes the account on the code, and asks for a password first', function () {
    $sms = phoneSms();

    phoneWeb()->get(route('phone.sign-in', ['next' => '/shop/checkout']))->assertOk()->assertSee('data-testid="phone-step"', false);
    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => '777 1111'])->assertRedirect(route('phone.sign-in'));
    expect(User::query()->where('phone', '+9607771111')->exists())->toBeFalse(); // nothing written before the code
    phoneWeb()->get(route('phone.sign-in'))->assertSee('data-testid="code-step"', false)->assertSee('data-testid="phone-name"', false);

    phoneWeb()->post(route('phone.sign-in.verify'), ['code' => $sms->lastCode(), 'name' => 'Mariyam Shop'])
        ->assertRedirect(route('account.set-password'));

    $user = User::query()->where('phone', '+9607771111')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Mariyam Shop')->and($user->force_password_change)->toBeTrue()
        ->and(UserContact::query()->where('user_id', $user->id)->where('type', 'mobile')->whereNotNull('verified_at')->exists())->toBeTrue();

    // Buying waits for the password.
    phoneWeb()->get(route('public.shop.checkout'))->assertRedirect(route('account.set-password'));

    phoneWeb()->post(route('account.set-password.store'), ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertRedirect();
    expect($user->fresh()->force_password_change)->toBeFalse();
});

it('asks a number with a password for the password, never a code, and signs in with it', function () {
    $sms = phoneSms();
    $user = User::factory()->create(['password' => Hash::make('secret-pass'), 'force_password_change' => false]);
    UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607771111', 'is_primary' => true, 'verified_at' => now()]);

    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => '7771111'])->assertRedirect(route('phone.sign-in'));
    phoneWeb()->get(route('phone.sign-in'))->assertSee('data-testid="password-step"', false)->assertSee('name="identifier" value="+9607771111"', false);
    expect($sms->codes)->toBe([]);

    phoneWeb()->post(route('login'), ['identifier' => '+9607771111', 'password' => 'secret-pass'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('signs a code account back in with a code until it has set a password', function () {
    $sms = phoneSms();
    $user = User::factory()->create(['force_password_change' => true]);
    UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607771111', 'is_primary' => true, 'verified_at' => now()]);

    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => '7771111']);
    phoneWeb()->get(route('phone.sign-in'))->assertSee('data-testid="code-step"', false)->assertDontSee('data-testid="phone-name"', false);
    phoneWeb()->post(route('phone.sign-in.verify'), ['code' => $sms->lastCode()])->assertRedirect(route('account.set-password'));
    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
});

it('claims the guest account a checkout made for the number, with its orders', function () {
    $sms = phoneSms();
    $guest = User::factory()->create(['name' => 'Guest Buyer', 'email' => null, 'phone' => '+9607771111', 'force_password_change' => true]);

    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => '7771111']);
    phoneWeb()->post(route('phone.sign-in.verify'), ['code' => $sms->lastCode()]);

    $this->assertAuthenticatedAs($guest);
    expect(UserContact::query()->where('user_id', $guest->id)->where('value', '+9607771111')->whereNotNull('verified_at')->exists())->toBeTrue()
        ->and(User::query()->where('phone', '+9607771111')->count())->toBe(1);
});

it('refuses a wrong code and signs nobody in', function () {
    phoneSms();
    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => '7771111']);
    phoneWeb()->post(route('phone.sign-in.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();
    expect(User::query()->where('phone', '+9607771111')->exists())->toBeFalse();
});

it('refuses a number that is not one', function () {
    phoneSms();
    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => 'hello'])->assertSessionHasErrors('phone');
    phoneWeb()->post(route('phone.sign-in.check'), ['phone' => '12'])->assertSessionHasErrors('phone');
});

it('does not send the code verify step without a code having been asked for', function () {
    phoneWeb()->post(route('phone.sign-in.verify'), ['code' => '123456'])->assertRedirect(route('phone.sign-in', ['again' => 1]));
    $this->assertGuest();
});

it('keeps the next address to this site', function () {
    phoneWeb()->get(route('phone.sign-in', ['next' => '//evil.test/x']))->assertOk();
    expect(session('url.intended'))->toBeNull();
    phoneWeb()->get(route('phone.sign-in', ['next' => '/shop/checkout']))->assertOk();
    expect(session('url.intended'))->toBe(url('/shop/checkout'));
});

it('does not ask a §5ly guest for a password: it has no number to sign in with', function () {
    $guest = User::factory()->create(['email' => null, 'phone' => '+9607772222', 'force_password_change' => true]);
    test()->actingAs($guest);
    phoneWeb()->get(route('public.shop.checkout'))->assertRedirect(route('public.shop.cart'));
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        expect(__('account.phone_title', [], $locale))->not->toBe(__('account.phone_title', [], 'en'))
            ->and(__('account.phone_set_password_now', [], $locale))->not->toBe('account.phone_set_password_now');
    }
});
