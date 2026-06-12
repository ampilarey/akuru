<?php

namespace App\Domains\Notifications\Contracts;

interface PushSenderInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendToDevice(string $deviceToken, array $payload): bool;
}
