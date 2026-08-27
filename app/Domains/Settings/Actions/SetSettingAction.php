<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\Setting;

class SetSettingAction
{
    public function execute(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'general',
        ?string $label = null,
    ): void {
        $stored = match (true) {
            is_array($value) => json_encode($value),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'type' => $type,
                'group' => $group,
                'label' => $label ?? $key,
            ],
        );
    }
}
