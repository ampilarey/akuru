<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

class ResolveRegisterLockDaysAction
{
    public function __construct(private SettingsRepositoryInterface $settings) {}

    public function execute(): int
    {
        $value = $this->settings->get('register_lock_days');
        $days = (int) ($value ?? config('academics.register_lock_days', 7));

        return max(1, $days);
    }
}
