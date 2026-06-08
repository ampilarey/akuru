<?php

namespace App\Enums\Hifz;

enum HifzMistakeType: string
{
    case Haraka = 'haraka';
    case WrongLetter = 'wrong_letter';
    case WrongWord = 'wrong_word';
    case SkippedWord = 'skipped_word';
    case ExtraWord = 'extra_word';
    case WrongAyah = 'wrong_ayah';
    case WrongOrder = 'wrong_order';
    case Hesitation = 'hesitation';
    case WeakFluency = 'weak_fluency';
    case WaqfIbtida = 'waqf_ibtida';
    case Tajweed = 'tajweed';
    case Other = 'other';
}
