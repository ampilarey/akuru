<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\Device;

/**
 * The app signs out, or the person removes a phone from their list: the
 * device stops receiving. By token (the app's own, at sign-out) or by id (a
 * row in the person's list) — either way only the person's own devices, so
 * nobody can silence somebody else's phone by guessing.
 */
class ForgetDeviceAction
{
    public function byToken(int $userId, string $token): bool
    {
        return Device::query()->where('user_id', $userId)->where('token', trim($token))->update(['is_active' => false]) > 0;
    }

    public function byId(int $userId, int $deviceId): bool
    {
        return Device::query()->where('user_id', $userId)->whereKey($deviceId)->delete() > 0;
    }
}
