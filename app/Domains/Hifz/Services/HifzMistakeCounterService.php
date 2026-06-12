<?php

namespace App\Domains\Hifz\Services;

use App\Domains\Hifz\Models\HifzMistake;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Enums\Hifz\HifzMistakeType;

class HifzMistakeCounterService
{
    public function __construct(
        protected HifzScoringService $scoringService
    ) {}

    public function syncRecord(HifzSessionRecord $record): HifzSessionRecord
    {
        $mistakes = $record->mistakes;

        $record->mistake_count = $mistakes->count();
        $record->haraka_mistakes = $mistakes->where('mistake_type', HifzMistakeType::Haraka)->count();
        $record->word_mistakes = $mistakes->whereIn('mistake_type', [
            HifzMistakeType::WrongLetter,
            HifzMistakeType::WrongWord,
            HifzMistakeType::SkippedWord,
            HifzMistakeType::ExtraWord,
        ])->count();
        $record->fluency_mistakes = $mistakes->whereIn('mistake_type', [
            HifzMistakeType::WeakFluency,
            HifzMistakeType::Hesitation,
        ])->count();

        if ($record->haraka_mistakes >= 3 || $record->mistake_count >= 5) {
            $record->requires_supervisor_review = true;
        }

        $this->scoringService->applyToRecord($record);
        $record->save();

        return $record->fresh();
    }

    public function afterMistakeChange(HifzMistake $mistake): HifzSessionRecord
    {
        return $this->syncRecord($mistake->sessionRecord);
    }
}
