<?php

namespace App\Domains\HR\Actions;

use Illuminate\Support\Facades\DB;

class ResolveHrSettingsAction
{
    /**
     * @return array{staff_self_checkin: bool}
     */
    public function execute(): array
    {
        $value = DB::table('settings')->where('key', 'hr.staff_self_checkin')->value('value');

        return [
            'staff_self_checkin' => filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
