<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\MemorizationStatus;
use App\Domains\Courses\Components\Quran\Enums\QuranMistakeSeverity;
use App\Domains\Courses\Components\Quran\Enums\QuranMistakeType;
use App\Domains\Courses\Components\Quran\Enums\RecitationSubmissionStatus;
use App\Domains\Courses\Components\Quran\Models\QuranMistakeMark;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §52.19–52.20 review loop, shaped like the engine's ReviewAttemptAction:
 * a teacher outcome closes the submission, mistake marks land with it, and the
 * student's memorization progress row follows the outcome. Marks derive their
 * type haraka-strictly (§52.2 via DeriveHarakaMistakeAction) when letter ids
 * are given without an explicit type.
 */
class ReviewRecitationAction
{
    private const TEACHER_OUTCOMES = [
        RecitationSubmissionStatus::TeacherReviewed,
        RecitationSubmissionStatus::NeedsRepeat,
        RecitationSubmissionStatus::Passed,
        RecitationSubmissionStatus::Failed,
    ];

    /**
     * @param  array<string, mixed>  $review
     */
    public function execute(int $submissionId, array $review): QuranRecitationSubmission
    {
        $submission = QuranRecitationSubmission::query()->findOrFail($submissionId);

        $status = RecitationSubmissionStatus::tryFrom((string) ($review['status'] ?? ''));
        if ($status === null || ! in_array($status, self::TEACHER_OUTCOMES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Review status must be teacher_reviewed, needs_repeat, passed or failed.',
            ]);
        }

        $mistakes = is_array($review['mistakes'] ?? null) ? $review['mistakes'] : [];

        return DB::transaction(function () use ($submission, $status, $mistakes, $review) {
            foreach ($mistakes as $index => $mistake) {
                $type = isset($mistake['mistake_type']) && $mistake['mistake_type'] !== ''
                    ? QuranMistakeType::tryFrom((string) $mistake['mistake_type'])
                    : app(DeriveHarakaMistakeAction::class)->execute(
                        isset($mistake['expected_letter_id']) ? (int) $mistake['expected_letter_id'] : null,
                        isset($mistake['expected_haraka_id']) ? (int) $mistake['expected_haraka_id'] : null,
                        isset($mistake['predicted_letter_id']) ? (int) $mistake['predicted_letter_id'] : null,
                        isset($mistake['predicted_haraka_id']) ? (int) $mistake['predicted_haraka_id'] : null,
                    );
                if ($type === null) {
                    throw ValidationException::withMessages([
                        "mistakes.{$index}.mistake_type" => 'Mistake type is required when it cannot be derived from letter/haraka ids.',
                    ]);
                }

                $severity = QuranMistakeSeverity::tryFrom(
                    (string) ($mistake['severity'] ?? QuranMistakeSeverity::Minor->value)
                );
                if ($severity === null) {
                    throw ValidationException::withMessages([
                        "mistakes.{$index}.severity" => 'Invalid mistake severity.',
                    ]);
                }

                QuranMistakeMark::query()->create([
                    'quran_recitation_submission_id' => $submission->id,
                    'surah_id' => $mistake['surah_id'] ?? $submission->surah_id,
                    'ayah_number' => $mistake['ayah_number'] ?? null,
                    'word_position' => $mistake['word_position'] ?? null,
                    'expected_letter_id' => $mistake['expected_letter_id'] ?? null,
                    'expected_haraka_id' => $mistake['expected_haraka_id'] ?? null,
                    'predicted_letter_id' => $mistake['predicted_letter_id'] ?? null,
                    'predicted_haraka_id' => $mistake['predicted_haraka_id'] ?? null,
                    'mistake_type' => $type,
                    'severity' => $severity,
                    'teacher_id' => $review['teacher_id'] ?? null,
                    'comment' => $mistake['comment'] ?? null,
                    'audio_start_ms' => $mistake['audio_start_ms'] ?? null,
                    'audio_end_ms' => $mistake['audio_end_ms'] ?? null,
                ]);
            }

            $submission->fill([
                'status' => $status,
                'reviewed_by' => $review['reviewed_by'] ?? null,
                'reviewed_at' => now(),
                'review_note' => $review['note'] ?? null,
            ]);
            $submission->save();

            if ($submission->surah_id !== null) {
                app(SaveMemorizationProgressAction::class)->execute([
                    'student_id' => (int) $submission->student_id,
                    'surah_id' => (int) $submission->surah_id,
                    'start_ayah_number' => $submission->start_ayah_number,
                    'end_ayah_number' => $submission->end_ayah_number,
                    'academic_year_id' => $submission->academic_year_id,
                    'status' => $this->memorizationStatus($status)->value,
                    'last_reviewed_at' => now(),
                    'mistake_count' => count($mistakes),
                    'teacher_id' => $review['teacher_id'] ?? null,
                ]);
            }

            return $submission->refresh();
        });
    }

    private function memorizationStatus(RecitationSubmissionStatus $status): MemorizationStatus
    {
        return match ($status) {
            RecitationSubmissionStatus::Passed => MemorizationStatus::Passed,
            RecitationSubmissionStatus::NeedsRepeat => MemorizationStatus::NeedsRevision,
            RecitationSubmissionStatus::Failed => MemorizationStatus::Weak,
            default => MemorizationStatus::Submitted,
        };
    }
}
