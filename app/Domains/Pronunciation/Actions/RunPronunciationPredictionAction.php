<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use App\Domains\Pronunciation\Models\AiPrediction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use Illuminate\Support\Facades\DB;

/**
 * §51.11/§51.12: run the contract-bound predictor on an attempt's audio
 * and store the prediction with the §51.12 final status. Compared against
 * the EXPECTED letter/haraka by slug (labels are the dataset folder
 * names). Low confidence or any mismatch keeps teacher review required;
 * a confident correct answer downgrades it to a spot-check.
 */
class RunPronunciationPredictionAction
{
    public function execute(int $attemptId): ?AiPrediction
    {
        $attempt = ArabicPronunciationAttempt::query()->findOrFail($attemptId);
        if ($attempt->audio_media_file_id === null) {
            return null;
        }

        $media = app(ReadPrivateMediaAction::class)->execute((int) $attempt->audio_media_file_id);
        if ($media === null) {
            return null;
        }

        // The predictor wants a file path; media may live on any backend.
        $temp = tempnam(sys_get_temp_dir(), 'pron_');
        file_put_contents($temp, $media['contents']);

        try {
            $result = app(PronunciationPredictionInterface::class)->predict($temp);
        } finally {
            @unlink($temp);
        }

        // Table-level reference resolution — no cross-domain model imports.
        // Labels are the dataset folder names, which are the key_names.
        $predictedLetterId = $result->predictedLetter !== null
            ? DB::table('arabic_letters')->where('key_name', $result->predictedLetter)->value('id')
            : null;
        $predictedHarakaId = $result->predictedHaraka !== null
            ? DB::table('arabic_harakas')->where('key_name', $result->predictedHaraka)->value('id')
            : null;

        $threshold = (float) config('ai.confidence_threshold', 0.70);
        $letterMatch = $result->success && $predictedLetterId !== null && (int) $predictedLetterId === (int) $attempt->expected_letter_id;
        $harakaMatch = $result->success && $predictedHarakaId !== null && (int) $predictedHarakaId === (int) $attempt->expected_haraka_id;
        $confident = $result->success
            && ($result->letterConfidence ?? 0) >= $threshold
            && ($result->harakaConfidence ?? 0) >= $threshold;

        $finalStatus = match (true) {
            ! $result->success => 'error',
            ! $confident => 'low_confidence',
            $letterMatch && $harakaMatch => 'correct',
            ! $letterMatch => 'wrong_letter',
            default => 'wrong_haraka',
        };

        $prediction = AiPrediction::query()->create([
            'arabic_pronunciation_attempt_id' => $attempt->id,
            'predicted_letter_id' => $predictedLetterId,
            'predicted_haraka_id' => $predictedHarakaId,
            'predicted_letter_label' => $result->predictedLetter,
            'predicted_haraka_label' => $result->predictedHaraka,
            'letter_confidence' => $result->letterConfidence,
            'haraka_confidence' => $result->harakaConfidence,
            'is_letter_match' => $result->success ? $letterMatch : null,
            'is_haraka_match' => $result->success ? $harakaMatch : null,
            'final_status' => $finalStatus,
            'raw_json' => $result->raw !== null ? json_encode($result->raw) : null,
            'model_version' => $result->modelVersion,
            'error_message' => $result->error,
        ]);

        $attempt->ai_prediction_id = $prediction->id;
        $attempt->status = $result->success ? 'ai_checked' : 'submitted';
        // A confident, fully correct answer becomes a spot-check; everything
        // else stays in the human queue (§51.16).
        $attempt->teacher_review_required = $finalStatus !== 'correct';
        $attempt->save();

        return $prediction;
    }
}
