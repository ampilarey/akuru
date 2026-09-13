<?php

namespace App\Domains\HR\Actions;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

class ResolveHrSettingsAction
{
    public function __construct(private SettingsRepositoryInterface $settings) {}

    /**
     * @return array{staff_self_checkin: bool}
     */
    public function execute(): array
    {
        $value = $this->settings->get('hr.staff_self_checkin');

        return [
            'staff_self_checkin' => filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
