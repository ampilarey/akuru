<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;

/**
 * How many accounts, asked of Identity rather than counted on a dashboard.
 */
class CountUsersAction
{
    /** Accounts created today. */
    public function today(): int
    {
        return User::query()->whereDate('created_at', today())->count();
    }

    /** Every account. */
    public function total(): int
    {
        return User::query()->count();
    }
}
