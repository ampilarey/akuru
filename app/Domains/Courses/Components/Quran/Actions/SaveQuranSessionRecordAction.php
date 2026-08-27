<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\QuranLaneResult;
use App\Domains\Courses\Components\Quran\Enums\QuranRevisionResult;
use App\Domains\Courses\Components\Quran\Enums\QuranSessionOverallStatus;
use App\Domains\Courses\Components\Quran\Models\QuranSessionRecord;
use App\Domains\Offerings\Actions\ListSessionAttendanceAction;
use App\Domains\Offerings\Actions\RecordOfferingAttendanceAction;
use App\Support\Contracts\QuranReferenceReader;
use Illuminate\Validation\ValidationException;

/**
 * F5-P1: upsert one student's three-lane record for an engine session.
 * Roster membership is proven through the same engine action the sheet
 * reads from; attendance_status, when given, is written through
 * RecordOfferingAttendanceAction so attendance_records stays the single
 * source (rule 11 discipline — this table never stores attendance).
 */
class SaveQuranSessionRecordAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $sessionId, array $data): QuranSessionRecord
    {
        $sheet = app(ListSessionAttendanceAction::class)->execute($sessionId);
        $enrollmentId = (int) ($data['course_enrollment_id'] ?? 0);
        $rosterRow = collect($sheet['roster'])->firstWhere('enrollment_id', $enrollmentId);
        if ($rosterRow === null) {
            throw ValidationException::withMessages([
                'course_enrollment_id' => 'Enrollment is not on this session\'s offering.',
            ]);
        }

        $this->assertEnum($data, 'new_result', QuranLaneResult::class);
        $this->assertEnum($data, 'recent_revision_result', QuranRevisionResult::class);
        $this->assertEnum($data, 'old_revision_result', QuranRevisionResult::class);
        $this->assertEnum($data, 'overall_status', QuranSessionOverallStatus::class);

        $reader = app(QuranReferenceReader::class);
        foreach (['new_from_surah_id', 'new_to_surah_id'] as $key) {
            if (! empty($data[$key]) && $reader->findSurah((int) $data[$key]) === null) {
                throw ValidationException::withMessages([$key => 'Unknown surah.']);
            }
        }

        $counts = [
            'haraka_mistakes' => max(0, (int) ($data['haraka_mistakes'] ?? 0)),
            'word_mistakes' => max(0, (int) ($data['word_mistakes'] ?? 0)),
            'fluency_mistakes' => max(0, (int) ($data['fluency_mistakes'] ?? 0)),
        ];
        $mistakeCount = isset($data['mistake_count'])
            ? max(0, (int) $data['mistake_count'])
            : array_sum($counts);

        $record = QuranSessionRecord::query()->firstOrNew([
            'course_offering_session_id' => $sessionId,
            'course_enrollment_id' => $enrollmentId,
        ]);
        $record->fill([
            'student_id' => (int) $rosterRow['student_id'],
            'academic_year_id' => $sheet['session']['academic_year_id'] ?? null,
            'new_from_surah_id' => $data['new_from_surah_id'] ?? null,
            'new_from_ayah' => $data['new_from_ayah'] ?? null,
            'new_to_surah_id' => $data['new_to_surah_id'] ?? null,
            'new_to_ayah' => $data['new_to_ayah'] ?? null,
            'new_result' => $data['new_result'] ?? null,
            'new_score' => $data['new_score'] ?? null,
            'recent_revision_text' => $data['recent_revision_text'] ?? null,
            'recent_revision_result' => $data['recent_revision_result'] ?? null,
            'recent_revision_score' => $data['recent_revision_score'] ?? null,
            'old_revision_text' => $data['old_revision_text'] ?? null,
            'old_revision_result' => $data['old_revision_result'] ?? null,
            'old_revision_score' => $data['old_revision_score'] ?? null,
            'mistake_count' => $mistakeCount,
            ...$counts,
            'teacher_note' => $data['teacher_note'] ?? null,
            'parent_visible_note' => $data['parent_visible_note'] ?? null,
            'next_target' => $data['next_target'] ?? null,
            'requires_parent_attention' => (bool) ($data['requires_parent_attention'] ?? false),
            'requires_supervisor_review' => (bool) ($data['requires_supervisor_review'] ?? false),
            'overall_status' => $data['overall_status'] ?? null,
        ]);
        if (! $record->exists) {
            $record->created_by = $data['created_by'] ?? null;
        }
        $record->save();

        if (! empty($data['attendance_status'])) {
            app(RecordOfferingAttendanceAction::class)->execute([
                'course_offering_session_id' => $sessionId,
                'enrollment_id' => $enrollmentId,
                'status' => (string) $data['attendance_status'],
                'attendance_mode' => 'physical',
                'marked_by' => $data['created_by'] ?? null,
            ]);
        }

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  class-string  $enum
     */
    private function assertEnum(array $data, string $key, string $enum): void
    {
        if (! empty($data[$key]) && $enum::tryFrom((string) $data[$key]) === null) {
            throw ValidationException::withMessages([$key => 'Invalid value.']);
        }
    }
}
