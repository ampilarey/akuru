<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

class ResolveRegisterLockDaysAction
{
    public function execute(): int
    {
        $value = DB::table('settings')->where('key', 'register_lock_days')->value('value');
        $days = (int) ($value ?? config('academics.register_lock_days', 7));

        return max(1, $days);
    }
}
