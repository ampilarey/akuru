<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountResolverService
{
    public function __construct(
        protected ContactNormalizer $normalizer
    ) {}

    /**
     * Resolve or create user and contact by normalized type+value.
     *
     * Race-safe: retries on duplicate-key if two requests arrive simultaneously.
     *
     * @return array{0: User, 1: UserContact, 2: bool} [user, contact, isNewUser]
     */
    public function resolveOrCreateByContact(string $type, string $value): array
    {
        $normalized = $this->normalizer->normalize($type, $value);

        // Fast path: contact already exists
        $contact = UserContact::where('type', $type)->where('value', $normalized)->first();
        if ($contact) {
            return [$contact->user, $contact, false];
        }

        // Attempt to create; retry once on duplicate key (race condition)
        try {
            return DB::transaction(function () use ($type, $normalized) {
                // Re-check inside transaction to avoid TOCTOU
                $existing = UserContact::where('type', $type)->where('value', $normalized)->lockForUpdate()->first();
                if ($existing) {
                    return [$existing->user, $existing, false];
                }

                $user = User::create([
                    'name'                 => 'User',
                    'email'                => $type === 'email' ? $normalized : null,
                    'password'             => Hash::make(Str::random(40)),
                    'force_password_change' => true,
                ]);

                $contact = UserContact::create([
                    'user_id'     => $user->id,
                    'type'        => $type,
                    'value'       => $normalized,
                    'is_primary'  => true,
                    'verified_at' => null,
                ]);

                return [$user, $contact, true];
            });
        } catch (UniqueConstraintViolationException $e) {
            // Another request won the race – fetch the winner's record
            $contact = UserContact::where('type', $type)->where('value', $normalized)->firstOrFail();
            return [$contact->user, $contact, false];
        }
    }
}
