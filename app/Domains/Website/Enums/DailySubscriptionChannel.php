<?php

namespace App\Domains\Website\Enums;

enum DailySubscriptionChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Push = 'push';
}
