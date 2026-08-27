<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\Setting;

class GetSettingAction
{
    public function execute(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}
