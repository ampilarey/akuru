<?php

namespace App\Domains\PrayerTimes\Enums;

enum PrayerBroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
