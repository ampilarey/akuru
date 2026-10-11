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

    /**
     * Only a draft or a previewed broadcast can be changed or previewed again.
     * Once queued, its recipients are its send record: previewing it again
     * would delete them and confirming would send the same SMS twice.
     */
    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Previewed;
    }
}
