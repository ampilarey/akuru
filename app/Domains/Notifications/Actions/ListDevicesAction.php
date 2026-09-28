<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Device;

/**
 * A person's registered phones, as the notification centre lists them.
 */
class ListDevicesAction
{
    /**
     * @return list<array{id: int, platform: string, name: ?string, app_version: ?string, active: bool, last_seen_at: ?string}>
     */
    public function execute(int $userId): array
    {
        return Device::query()
            ->where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (Device $device) => [
                'id' => (int) $device->id,
                'platform' => (string) $device->platform,
                'name' => $device->device_name,
                'app_version' => $device->app_version,
                'active' => (bool) $device->is_active,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
