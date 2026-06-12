<?php

namespace App\Domains\Notifications\Contracts;

interface SmsSenderInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): array;
}
