<?php

namespace App\Domains\PrayerTimes\Enums;

enum PrayerBroadcastStatus: string
{
    case Draft = 'draft';
    case Previewed = 'previewed';
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
