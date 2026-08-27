<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\UserContact;

class ReadVerifiedUserContactsAction
{
    /**
     * @return array{email: ?string, phone: ?string}
     */
    public function execute(int $userId): array
    {
        $contacts = UserContact::query()
            ->where('user_id', $userId)
            ->whereNotNull('verified_at')
            ->orderByDesc('is_primary')
            ->get();

        $email = $contacts->first(fn (UserContact $row) => $row->type === 'email');
        $phone = $contacts->first(fn (UserContact $row) => $row->type === 'mobile');

        return [
            'email' => $email?->value,
            'phone' => $phone?->value,
        ];
    }
}
