<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\AiPrediction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use App\Domains\Pronunciation\Models\TrainingSample;
use Illuminate\Validation\ValidationException;

/**
 * §51.16 steps 3–6: a human listens and confirms or corrects. The verdict
 * sets the verified letter/haraka; a usable recording becomes a
 * pending-review training sample; unclear audio is rejected outright and
 * never enters the dataset.
 */
class ReviewPronunciationAttemptAction
{
    /**
     * @param  array{verified_letter_id?: int, verified_haraka_id?: int, reject?: bool, rejection_reason?: string, notes?: string}  $data
     */
    public function execute(int $attemptId, int $reviewerUserId, array $data): ArabicPronunciationAttempt
    {
        $attempt = ArabicPronunciationAttempt::query()->findOrFail($attemptId);
        if ($attempt->status === 'teacher_reviewed') {
            throw ValidationException::withMessages(['attempt' => 'This attempt has already been reviewed.']);
        }

        $prediction = $attempt->ai_prediction_id !== null
            ? AiPrediction::query()->find($attempt->ai_prediction_id)
            : null;

        if (empty($data['reject'])) {
            if (empty($data['verified_letter_id']) || empty($data['verified_haraka_id'])) {
                throw ValidationException::withMessages(['verified_letter_id' => 'Set the verified letter and haraka, or reject the sample.']);
            }
            if ($attempt->audio_media_file_id !== null) {
                TrainingSample::query()->create([
                    'arabic_pronunciation_attempt_id' => $attempt->id,
                    'audio_media_file_id' => $attempt->audio_media_file_id,
                    'verified_letter_id' => (int) $data['verified_letter_id'],
                    'verified_haraka_id' => (int) $data['verified_haraka_id'],
                    'original_predicted_letter_id' => $prediction?->predicted_letter_id,
                    'original_predicted_haraka_id' => $prediction?->predicted_haraka_id,
                    'reviewed_by_user_id' => $reviewerUserId,
                    'status' => 'pending_review',
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        }

        $attempt->status = 'teacher_reviewed';
        $attempt->teacher_review_required = false;
        $attempt->save();

        return $attempt->refresh();
    }
}
