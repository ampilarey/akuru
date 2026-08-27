<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;

class FindUserIdByVerifiedMobileAction
{
    public function execute(string $rawPhone): ?int
    {
        $normalized = app(ContactNormalizer::class)->normalizePhone($rawPhone);
        if ($normalized === '' || $normalized === '+') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            ltrim($normalized, '+'),
            $rawPhone,
        ])));

        $userId = UserContact::query()
            ->where('type', 'mobile')
            ->whereNotNull('verified_at')
            ->whereIn('value', $candidates)
            ->value('user_id');

        return $userId !== null ? (int) $userId : null;
    }
}
