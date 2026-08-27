<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use Illuminate\Http\UploadedFile;

/**
 * §51.13: a student's isolated letter+haraka recording. Audio goes to
 * PRIVATE media; the attempt starts life needing teacher review — the AI
 * (when enabled) may later downgrade that to a spot-check (§51.16 keeps
 * humans in the loop either way).
 */
class StoreArabicPronunciationAttemptAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $studentUserId, array $data, ?UploadedFile $audio = null): ArabicPronunciationAttempt
    {
        $mediaId = null;
        if ($audio !== null) {
            $stored = app(StorePrivateMediaAction::class)->execute($audio, $studentUserId);
            $mediaId = $stored['id'];
        }

        $attempt = ArabicPronunciationAttempt::query()->create([
            'student_user_id' => $studentUserId,
            'course_id' => $data['course_id'] ?? null,
            'course_offering_id' => $data['course_offering_id'] ?? null,
            'activity_id' => $data['activity_id'] ?? null,
            'expected_letter_id' => (int) $data['expected_letter_id'],
            'expected_haraka_id' => (int) $data['expected_haraka_id'],
            'audio_media_file_id' => $mediaId,
            'mode' => in_array($data['mode'] ?? 'manual', ['live', 'manual'], true) ? ($data['mode'] ?? 'manual') : 'manual',
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'status' => 'submitted',
            'teacher_review_required' => true,
        ]);

        // With the flag on, the AI checks immediately; a failure inside the
        // predictor leaves the attempt in the human queue — never an error
        // for the student (rule 8).
        if (config('ai.pronunciation_enabled') && $mediaId !== null) {
            app(RunPronunciationPredictionAction::class)->execute($attempt->id);
            $attempt->refresh();
        }

        return $attempt;
    }
}
