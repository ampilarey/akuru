<?php

namespace App\Domains\Website\Enums;

enum DailyDeliveryStatus: string
{
    case Sent = 'sent';
    case SkippedEmpty = 'skipped_empty';
    case Failed = 'failed';
}
