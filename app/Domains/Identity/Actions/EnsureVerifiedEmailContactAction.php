<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;

class EnsureVerifiedEmailContactAction
{
    public function execute(User $user): ?UserContact
    {
        $email = app(ContactNormalizer::class)->normalizeEmail((string) $user->email);
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        return UserContact::query()->firstOrCreate(
            ['type' => 'email', 'value' => $email],
            [
                'user_id' => $user->id,
                'is_primary' => true,
                'verified_at' => now(),
            ],
        );
    }
}
