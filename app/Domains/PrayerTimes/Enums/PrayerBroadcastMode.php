<?php

namespace App\Domains\PrayerTimes\Enums;

enum PrayerBroadcastMode: string
{
    case Daily = 'daily';
    case Range = 'range';
    case ChangeOnly = 'change_only';
}
