<?php

namespace App\Domains\Website\Enums;

enum DailyContentType: string
{
    case Ayah = 'ayah';
    case Hadith = 'hadith';
    case Saying = 'saying';
    case Reminder = 'reminder';
}
