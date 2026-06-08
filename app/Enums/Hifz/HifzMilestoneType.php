<?php

namespace App\Enums\Hifz;

enum HifzMilestoneType: string
{
    case SurahCompleted = 'surah_completed';
    case JuzCompleted = 'juz_completed';
    case PageCompleted = 'page_completed';
    case QuranCompleted = 'quran_completed';
    case Custom = 'custom';
}
