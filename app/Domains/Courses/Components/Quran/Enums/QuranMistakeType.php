<?php

namespace App\Domains\Courses\Components\Quran\Enums;

/**
 * SPEC §52.20. wrong_letter vs wrong_haraka is the §52.2 critical haraka
 * rule — the letter and the vowel are judged separately.
 */
enum QuranMistakeType: string
{
    case WrongLetter = 'wrong_letter';
    case WrongHaraka = 'wrong_haraka';
    case MissedWord = 'missed_word';
    case AddedWord = 'added_word';
    case RepeatedWord = 'repeated_word';
    case WrongWord = 'wrong_word';
    case PronunciationIssue = 'pronunciation_issue';
    case WaqfIssue = 'waqf_issue';
    case MaddIssue = 'madd_issue';
    case GhunnahIssue = 'ghunnah_issue';
    case TajweedIssue = 'tajweed_issue';
    case Other = 'other';
}
