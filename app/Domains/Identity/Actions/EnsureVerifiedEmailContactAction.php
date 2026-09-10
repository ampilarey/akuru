<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;

/**
 * Mirror a user's email into `user_contacts` as a verified contact.
 *
 * This action existed and **nothing called it**, which is not a tidiness
 * problem: OTP password reset resolves an account by looking up
 * `user_contacts`, not `users.email`. An account created by staff — every
 * teacher, every student account made through the People screens — therefore
 * had no email contact row, and "reset my password by email" silently did
 * nothing. The flow deliberately does not reveal whether an account exists, so
 * the person is told a code has been sent and simply never receives one. No
 * error, no support signal.
 *
 * `user_contacts(type, value)` is **globally unique**, so the previous
 * `firstOrCreate` could hand back a row belonging to somebody else and the
 * caller would believe it had ensured a contact for this user. Two accounts
 * sharing an email address is a data problem for a human to resolve, not
 * something to paper over by pointing one person's reset flow at another
 * person's account.
 */
class EnsureVerifiedEmailContactAction
{
    public function execute(User $user): ?UserContact
    {
        $email = app(ContactNormalizer::class)->normalizeEmail((string) $user->email);
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        $existing = UserContact::query()
            ->where('type', 'email')
            ->where('value', $email)
            ->first();

        if ($existing !== null) {
            // Already ours: nothing to do. Somebody else's: leave it alone and
            // say so by returning null, rather than claiming success.
            return (int) $existing->user_id === (int) $user->id ? $existing : null;
        }

        return UserContact::query()->create([
            'type' => 'email',
            'value' => $email,
            'user_id' => $user->id,
            'is_primary' => true,
            // Verified because it is the address the account itself was created
            // with — the same standing the enrolment paths already give it.
            'verified_at' => now(),
        ]);
    }
}
