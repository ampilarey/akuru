<?php

namespace App\Domains\Website\Enums;

enum DailySubscriptionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
}
