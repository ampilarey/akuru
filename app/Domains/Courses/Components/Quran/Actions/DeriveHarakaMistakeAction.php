<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\QuranMistakeType;

/**
 * SPEC §52.2, the critical haraka rule: letter and vowel are judged
 * separately. Expected بَ read as بِ is letter-correct, haraka-wrong —
 * a haraka mistake, not a letter mistake. Works on ids alone so this
 * component never references the Arabic component's code (rule 3).
 */
class DeriveHarakaMistakeAction
{
    public function execute(
        ?int $expectedLetterId,
        ?int $expectedHarakaId,
        ?int $predictedLetterId,
        ?int $predictedHarakaId,
    ): ?QuranMistakeType {
        if ($expectedLetterId === null || $predictedLetterId === null) {
            return null;
        }

        if ($expectedLetterId !== $predictedLetterId) {
            return QuranMistakeType::WrongLetter;
        }

        if ($expectedHarakaId !== null
            && $predictedHarakaId !== null
            && $expectedHarakaId !== $predictedHarakaId) {
            return QuranMistakeType::WrongHaraka;
        }

        return null;
    }
}
