<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Pronunciation\Contracts\PronunciationPredictionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shared core for BOTH consumers (§52.3 — one model family): read the
 * private audio, ask the contract, classify against the expected
 * letter+haraka with the §51.12 final statuses. Returns the prediction
 * attributes; callers persist with their own foreign key.
 */
class PredictIsolatedSoundAction
{
    /**
     * @return array<string, mixed>|null null when the audio cannot be read
     */
    public function execute(int $audioMediaFileId, int $expectedLetterId, int $expectedHarakaId): ?array
    {
        $media = app(ReadPrivateMediaAction::class)->execute($audioMediaFileId);
        if ($media === null) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'pron_');
        file_put_contents($temp, $media['contents']);
        try {
            $result = app(PronunciationPredictionInterface::class)->predict($temp);
        } finally {
            @unlink($temp);
        }

        // Labels are dataset folder names, which are the key_names.
        $predictedLetterId = $result->predictedLetter !== null
            ? DB::table('arabic_letters')->where('key_name', $result->predictedLetter)->value('id')
            : null;
        $predictedHarakaId = $result->predictedHaraka !== null
            ? DB::table('arabic_harakas')->where('key_name', $result->predictedHaraka)->value('id')
            : null;

        $threshold = (float) config('ai.confidence_threshold', 0.70);
        $letterMatch = $result->success && $predictedLetterId !== null && (int) $predictedLetterId === $expectedLetterId;
        $harakaMatch = $result->success && $predictedHarakaId !== null && (int) $predictedHarakaId === $expectedHarakaId;
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

        return [
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
        ];
    }
}
