<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\AiPrediction;

/**
 * Qur'an B (§52.3): the second consumer. Another domain hands over its
 * audio + expectation and gets a stored prediction back — Pronunciation
 * owns its models; consumers only call this action.
 */
class RecordExternalAudioPredictionAction
{
    public function execute(
        int $audioMediaFileId,
        int $expectedLetterId,
        int $expectedHarakaId,
        ?int $quranRecitationSubmissionId = null,
    ): ?AiPrediction {
        $attributes = app(PredictIsolatedSoundAction::class)->execute(
            $audioMediaFileId,
            $expectedLetterId,
            $expectedHarakaId,
        );
        if ($attributes === null) {
            return null;
        }

        return AiPrediction::query()->create(
            $attributes + ['quran_recitation_submission_id' => $quranRecitationSubmissionId],
        );
    }
}
