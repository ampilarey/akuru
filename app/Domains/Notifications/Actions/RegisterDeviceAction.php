<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Device;

/**
 * The mobile app registers the phone it runs on (SPEC §50, STATUS §5jr).
 *
 * A token names one installation, so it is the key: the same token seen again
 * refreshes the row (platform, name, version, locale, last seen) and switches
 * it back on; a token that was another person's — the phone was handed over
 * and somebody else signed in — moves to the person holding it now, because a
 * push must never reach a phone that is no longer theirs.
 */
class RegisterDeviceAction
{
    /**
     * @param  array{token: string, platform?: ?string, device_name?: ?string, app_version?: ?string, locale?: ?string}  $data
     */
    public function execute(int $userId, array $data): Device
    {
        $device = Device::query()->firstOrNew(['token' => trim($data['token'])]);
        $device->fill([
            'user_id' => $userId,
            'platform' => in_array($data['platform'] ?? null, ['android', 'ios', 'web'], true) ? $data['platform'] : 'web',
            'device_name' => isset($data['device_name']) ? mb_substr(trim((string) $data['device_name']), 0, 120) : $device->device_name,
            'app_version' => isset($data['app_version']) ? mb_substr(trim((string) $data['app_version']), 0, 40) : $device->app_version,
            'locale' => in_array($data['locale'] ?? null, ['en', 'dv', 'ar'], true) ? $data['locale'] : ($device->locale ?: 'en'),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        $device->save();

        return $device;
    }
}
