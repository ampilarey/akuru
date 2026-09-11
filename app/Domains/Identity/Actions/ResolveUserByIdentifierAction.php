<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;

/**
 * Who does this identifier belong to?
 *
 * Extracted from `LoginRequest::authenticate()`, which resolved the user and
 * logged them in in one breath. **E7's account linking needs the first half
 * without the second** — proving you own a second account must not sign you
 * into it — and copying this logic would have created a second source of truth
 * for the most security-sensitive lookup in the app (rule 11).
 *
 * The three identifier types and their asymmetries are unchanged, comments
 * included, because each one is a decision somebody had to debug:
 *
 *  - **Email** — a contact row is a deliberate state, so it wins when present:
 *    public course registration creates contacts with `verified_at = null`
 *    while OTP is pending, and those must stay locked out. But accounts created
 *    outside the contact-aware flows (Breeze registration, admin creation,
 *    seeders, console recovery) never get a contact row at all and were
 *    permanently unable to log in. The fallback to `users.email` covers exactly
 *    that case and grants nothing an unverified contact was withholding.
 *  - **Mobile** — contacts only, and verified ones only.
 *  - **National ID** — straight off `users`.
 *
 * Returns the user or null. **It deliberately does not check the password, the
 * active flag, or rate limits** — those are the caller's, and login and linking
 * answer them differently.
 */
class ResolveUserByIdentifierAction
{
    public function execute(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $normalizer = app(ContactNormalizer::class);

        if (str_contains($identifier, '@')) {
            $value = $normalizer->normalizeEmail($identifier);

            $contact = UserContact::where('type', 'email')->where('value', $value)->first();

            return $contact !== null
                ? ($contact->verified_at !== null ? $contact->user : null)
                : User::whereRaw('LOWER(email) = ?', [$value])->first();
        }

        if (preg_match('/^\+?[\d\s\-]+$/', $identifier)) {
            $value = $normalizer->normalizePhone($identifier);

            return UserContact::where('type', 'mobile')
                ->where('value', $value)
                ->whereNotNull('verified_at')
                ->first()?->user;
        }

        return User::whereRaw('LOWER(national_id) = ?', [strtolower($identifier)])->first();
    }
}
