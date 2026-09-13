<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §36 asks the teacher to "open student submissions", "play audio/voice
 * submissions" and "view uploaded files". None of the three had anything to
 * open: no route stored a file against an attempt, so `answers` only ever held
 * typed text and the review screen had nothing but text to show.
 *
 * Attachments live inside the attempt's existing `answers` JSON rather than in
 * a new column. `answers` is already the record of what the student handed in,
 * and a parallel table would be a second answer to the same question (rule 11).
 *
 * **Attachments are server-owned.** The browser holds the whole `answers`
 * object and posts it back on every autosave and submit, so a client could
 * otherwise name any media id it liked and have the teacher handed a link to
 * it. `reconcile()` is the invariant: the client may drop an attachment it no
 * longer wants, and may never add one. Only `attach()` — which has just stored
 * the file — puts an id in.
 */
class AttachAttemptMediaAction
{
    /**
     * @param  array{id: int, mime: string, original_name: string}  $media
     * @return array<string, mixed>
     */
    public function attach(
        int $activityId,
        int $enrollmentId,
        int $studentId,
        int $courseId,
        array $media,
        ?int $academicYearId = null,
    ): array {
        $attempt = $this->openAttempt($activityId, $enrollmentId);
        $now = now();

        if ($attempt === null) {
            $attempt = ActivityAttempt::query()->create([
                'activity_id' => $activityId,
                'enrollment_id' => $enrollmentId,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'academic_year_id' => $academicYearId,
                'attempt_number' => $this->nextNumber($activityId, $enrollmentId),
                'status' => ActivityAttemptStatus::InProgress,
                'answers' => [],
                'started_at' => $now,
                'last_saved_at' => $now,
            ]);
        }

        $answers = is_array($attempt->answers) ? $attempt->answers : [];
        $attachments = $this->normalize($answers['attachments'] ?? null);

        $entry = [
            'id' => (int) $media['id'],
            'mime' => (string) ($media['mime'] ?? ''),
            'original_name' => (string) ($media['original_name'] ?? ''),
        ];

        // Re-uploading the same file replaces its row rather than doubling it.
        $attachments = array_values(array_filter(
            $attachments,
            static fn (array $row): bool => $row['id'] !== $entry['id'],
        ));
        $attachments[] = $entry;

        $answers['attachments'] = $attachments;
        $attempt->update(['answers' => $answers, 'last_saved_at' => $now]);

        return app(SaveActivityAttemptAction::class)->serialize($attempt->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function detach(int $activityId, int $enrollmentId, int $mediaId): array
    {
        $attempt = $this->openAttempt($activityId, $enrollmentId);
        if ($attempt === null) {
            throw ValidationException::withMessages([
                'attempt' => ['There is no open attempt to remove a file from.'],
            ]);
        }

        $answers = is_array($attempt->answers) ? $attempt->answers : [];
        $answers['attachments'] = array_values(array_filter(
            $this->normalize($answers['attachments'] ?? null),
            static fn (array $row): bool => $row['id'] !== $mediaId,
        ));

        $attempt->update(['answers' => $answers, 'last_saved_at' => now()]);

        return app(SaveActivityAttemptAction::class)->serialize($attempt->fresh());
    }

    /**
     * Replace whatever the client claims under `attachments` with the stored
     * list, narrowed to the ids the client still lists. Remove is honoured;
     * add is not.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function reconcile(int $activityId, int $enrollmentId, array $answers): array
    {
        $attempt = $this->openAttempt($activityId, $enrollmentId);
        $stored = $attempt === null
            ? []
            : $this->normalize((is_array($attempt->answers) ? $attempt->answers : [])['attachments'] ?? null);

        if ($stored === []) {
            unset($answers['attachments']);

            return $answers;
        }

        $keep = array_map(
            static fn (array $row): int => $row['id'],
            $this->normalize($answers['attachments'] ?? null),
        );

        // A client that says nothing about attachments keeps them all: an
        // autosave posted by a page that predates the upload must not silently
        // throw the student's file away.
        $claimed = array_key_exists('attachments', $answers);

        $answers['attachments'] = array_values(array_filter(
            $stored,
            static fn (array $row): bool => ! $claimed || in_array($row['id'], $keep, true),
        ));

        return $answers;
    }

    /**
     * @return list<array{id: int, mime: string, original_name: string}>
     */
    public function normalize(mixed $value): array
    {
        $rows = [];
        foreach (is_array($value) ? $value : [] as $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : (int) $row;
            if ($id <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'mime' => is_array($row) ? (string) ($row['mime'] ?? '') : '',
                'original_name' => is_array($row) ? (string) ($row['original_name'] ?? '') : '',
            ];
        }

        return $rows;
    }

    private function openAttempt(int $activityId, int $enrollmentId): ?ActivityAttempt
    {
        return ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->where('status', ActivityAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();
    }

    private function nextNumber(int $activityId, int $enrollmentId): int
    {
        return ((int) ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->max('attempt_number')) + 1;
    }
}
