<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Contracts\PushSenderInterface;

class NullPushSender implements PushSenderInterface
{
    public function sendToDevice(string $deviceToken, array $payload): bool
    {
        return false;
    }
}
