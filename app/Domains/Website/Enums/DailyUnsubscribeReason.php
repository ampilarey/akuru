<?php

namespace App\Domains\Website\Enums;

enum DailyUnsubscribeReason: string
{
    case Link = 'link';
    case Keyword = 'keyword';
}
