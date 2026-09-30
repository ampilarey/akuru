<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Notifications\Actions\LiveSmsAllowedAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P1: a customer signs in on the phone number, the
 * Bake & Grill way. A number whose account has a password gets the
 * password box (the ordinary login, which already takes a phone); any
 * other number gets a six-digit code by SMS. The code proves the number,
 * so it becomes a verified mobile contact — a real sign-in — and the
 * account then has to set a password before it buys anything
 * (`RequireCustomerPassword`); from then on the number takes the password
 * only, and a code is for "Forgot password".
 *
 * Nothing is written before the code is entered: a new number's code
 * lives in the cache (`OtpService::sendForNewRegistration`), as course
 * registration's does. On a correct code, in this order:
 *  - a verified contact already → that account (it had no password yet);
 *  - an *unverified* contact (a registration begun, never finished) →
 *    that account, its contact now verified: the code proves the number;
 *  - a guest account from the checkout (§5ly: the number on `users.phone`,
 *    no contact) → the latest one is claimed, so its orders and purchases
 *    come with it (BACKLOG C12);
 *  - otherwise a new account.
 */
class PhoneSignInAction
{
    public const PASSWORD = 'password';

    public const CODE = 'code';

    public function __construct(
        private readonly ContactNormalizer $normalizer,
        private readonly OtpService $otp,
    ) {}

    /** Codes reach a phone here, or at least a log someone can read (never production without live SMS). */
    public static function codesAvailable(): bool
    {
        return app(LiveSmsAllowedAction::class)->execute() || ! app()->environment('production');
    }

    public function normalize(string $raw): string
    {
        $phone = $this->normalizer->normalizePhone($raw);
        if (strlen(ltrim($phone, '+')) < 7 || ! preg_match('/^\+?[\d\s\-]{7,20}$/', trim($raw))) {
            throw ValidationException::withMessages(['phone' => __('account.phone_invalid')]);
        }

        return $phone;
    }

    /**
     * Which box to show, sending the code when it is the code.
     *
     * @return array{mode: string, phone: string, known: bool}
     */
    public function start(string $rawPhone): array
    {
        $phone = $this->normalize($rawPhone);
        $contact = $this->verifiedContact($phone);
        $user = $contact?->user;

        if ($user !== null && ! $user->force_password_change) {
            return ['mode' => self::PASSWORD, 'phone' => $phone, 'known' => true];
        }
        if (! self::codesAvailable()) {
            throw ValidationException::withMessages(['phone' => __('account.phone_codes_off')]);
        }
        if ($contact !== null) {
            $this->otp->send($contact, 'login');
        } else {
            $this->otp->sendForNewRegistration('mobile', $phone);
        }

        return ['mode' => self::CODE, 'phone' => $phone, 'known' => $contact !== null];
    }

    /** The account the code signs in to; the caller starts the session. */
    public function verify(string $phone, string $code, ?string $name = null): User
    {
        $contact = $this->verifiedContact($phone);
        if ($contact !== null) {
            if ($contact->user !== null && ! $contact->user->force_password_change) {
                // A password was set since the code went out: the code path is closed.
                throw ValidationException::withMessages(['code' => __('account.phone_has_password')]);
            }
            $this->otp->verify($contact, 'login', $code);

            return $this->active($contact->user);
        }

        $this->otp->verifyForNewRegistration('mobile', $phone, $code);

        return $this->active(DB::transaction(function () use ($phone, $name) {
            $pending = UserContact::query()->where('type', 'mobile')->where('value', $phone)->lockForUpdate()->first();
            if ($pending !== null && $pending->user !== null) {
                $pending->update(['verified_at' => now()]);

                return $pending->user;
            }

            $guest = User::query()->where('phone', $phone)
                ->whereDoesntHave('contacts', fn ($q) => $q->where('type', 'mobile'))
                ->latest('id')->first();
            $user = $guest ?? User::query()->create([
                'name' => trim((string) $name) !== '' ? trim((string) $name) : __('account.phone_default_name'),
                'email' => null,
                'password' => Hash::make(Str::random(40)),
                'phone' => $phone,
                'is_active' => true,
                'force_password_change' => true,
            ]);
            if ($guest !== null && trim((string) $name) !== '' && $guest->name === '') {
                $guest->update(['name' => trim((string) $name)]);
            }
            UserContact::query()->create([
                'user_id' => $user->id,
                'type' => 'mobile',
                'value' => $phone,
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            return $user;
        }));
    }

    private function verifiedContact(string $phone): ?UserContact
    {
        return UserContact::query()->with('user')->where('type', 'mobile')->where('value', $phone)->whereNotNull('verified_at')->first();
    }

    private function active(?User $user): User
    {
        if ($user === null || ! $user->is_active) {
            throw ValidationException::withMessages(['code' => 'Your account is inactive. Please contact support.']);
        }

        return $user;
    }
}
