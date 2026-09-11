<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\AccountLinkEvent;
use App\Domains\Identity\Models\LinkedAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Prove you own a second account, and link it to the one you are signed into.
 *
 * **This endpoint takes a password, so it is a credential-checking endpoint**
 * and is rate limited per user and IP exactly as login is. Without that, a link
 * form is an unthrottled password oracle sitting behind an ordinary session.
 *
 * Every refusal returns the **same sentence**. "No such account" and "wrong
 * password" told apart is an account-enumeration oracle, and a signed-in
 * attacker probing which identifiers exist is the likeliest use of this form.
 *
 * Links are written **reciprocally** and verified at creation: proving both
 * sides earns switching in both directions.
 */
class LinkAccountAction
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function execute(User $actor, string $identifier, string $password, ?string $ip = null): User
    {
        $key = 'link-account|'.$actor->id.'|'.($ip ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many attempts. Try again in '
                    .ceil(RateLimiter::availableIn($key) / 60).' minutes.',
            ]);
        }

        $target = app(ResolveUserByIdentifierAction::class)->execute($identifier);

        $refuse = function (?User $target) use ($actor, $key, $ip): never {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            AccountLinkEvent::query()->create([
                'user_id' => (int) $actor->id,
                'target_user_id' => $target?->id,
                'action' => AccountLinkEvent::FAILED,
                'ip' => $ip,
            ]);

            // One sentence for every refusal — see the class comment.
            throw ValidationException::withMessages([
                'identifier' => 'Those details do not match another account you can link.',
            ]);
        };

        if ($target === null || ! Hash::check($password, $target->password)) {
            $refuse($target);
        }

        // Linking an account to itself would make the switcher offer you
        // yourself, which is confusing rather than dangerous — but it is still
        // not a second identity.
        if ((int) $target->id === (int) $actor->id) {
            $refuse($target);
        }

        if (! $target->is_active) {
            $refuse($target);
        }

        RateLimiter::clear($key);

        return DB::transaction(function () use ($actor, $target, $ip): User {
            foreach ([[$actor->id, $target->id], [$target->id, $actor->id]] as [$from, $to]) {
                LinkedAccount::query()->updateOrCreate(
                    ['user_id' => (int) $from, 'linked_user_id' => (int) $to],
                    ['verified_at' => now()],
                );
            }

            AccountLinkEvent::query()->create([
                'user_id' => (int) $actor->id,
                'target_user_id' => (int) $target->id,
                'action' => AccountLinkEvent::LINKED,
                'ip' => $ip,
            ]);

            return $target;
        });
    }
}
