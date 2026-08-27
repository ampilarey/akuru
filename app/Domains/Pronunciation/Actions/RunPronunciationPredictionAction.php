<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\AiPrediction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;

/**
 * §51.11/§51.12: run the contract-bound predictor on an attempt's audio
 * and store the prediction with the §51.12 final status. Low confidence
 * or any mismatch keeps teacher review required; a confident correct
 * answer downgrades it to a spot-check.
 */
class RunPronunciationPredictionAction
{
    public function execute(int $attemptId): ?AiPrediction
    {
        $attempt = ArabicPronunciationAttempt::query()->findOrFail($attemptId);
        if ($attempt->audio_media_file_id === null) {
            return null;
        }

        $attributes = app(PredictIsolatedSoundAction::class)->execute(
            (int) $attempt->audio_media_file_id,
            (int) $attempt->expected_letter_id,
            (int) $attempt->expected_haraka_id,
        );
        if ($attributes === null) {
            return null;
        }

        $prediction = AiPrediction::query()->create(
            $attributes + ['arabic_pronunciation_attempt_id' => $attempt->id],
        );

        $attempt->ai_prediction_id = $prediction->id;
        $attempt->status = $attributes['final_status'] !== 'error' ? 'ai_checked' : 'submitted';
        // A confident, fully correct answer becomes a spot-check; everything
        // else stays in the human queue (§51.16).
        $attempt->teacher_review_required = $attributes['final_status'] !== 'correct';
        $attempt->save();

        return $prediction;
    }
}
