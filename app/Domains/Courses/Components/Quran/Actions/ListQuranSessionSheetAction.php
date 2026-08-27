<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\QuranLaneResult;
use App\Domains\Courses\Components\Quran\Enums\QuranRevisionResult;
use App\Domains\Courses\Components\Quran\Enums\QuranSessionOverallStatus;
use App\Domains\Courses\Components\Quran\Models\QuranSessionRecord;
use App\Domains\Offerings\Actions\ListSessionAttendanceAction;
use App\Support\Contracts\QuranReferenceReader;

/**
 * F5-P1 teacher sheet: the engine session's roster (via the existing
 * attendance action — one roster source, attendance status included) with
 * each student's three-lane record layered on top, plus the pickers.
 */
class ListQuranSessionSheetAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $sessionId): array
    {
        $base = app(ListSessionAttendanceAction::class)->execute($sessionId);

        $records = QuranSessionRecord::query()
            ->where('course_offering_session_id', $sessionId)
            ->get()
            ->keyBy('course_enrollment_id');

        $base['roster'] = array_map(function (array $row) use ($records): array {
            $record = $records->get((int) $row['enrollment_id']);
            $row['record'] = $record ? $this->serialize($record) : null;

            return $row;
        }, $base['roster']);

        $base['surahs'] = array_map(fn (array $surah): array => [
            'id' => $surah['id'],
            'index' => $surah['index'],
            'english_name' => $surah['english_name'],
            'arabic_name' => $surah['arabic_name'],
            'ayah_count' => $surah['ayah_count'],
        ], app(QuranReferenceReader::class)->listSurahs());

        $base['options'] = [
            'lane_results' => array_map(fn ($case) => $case->value, QuranLaneResult::cases()),
            'revision_results' => array_map(fn ($case) => $case->value, QuranRevisionResult::cases()),
            'overall_statuses' => array_map(fn ($case) => $case->value, QuranSessionOverallStatus::cases()),
        ];

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(QuranSessionRecord $record): array
    {
        return [
            'id' => $record->id,
            'course_enrollment_id' => (int) $record->course_enrollment_id,
            'new_from_surah_id' => $record->new_from_surah_id,
            'new_from_ayah' => $record->new_from_ayah,
            'new_to_surah_id' => $record->new_to_surah_id,
            'new_to_ayah' => $record->new_to_ayah,
            'new_result' => $record->new_result?->value,
            'new_score' => $record->new_score,
            'recent_revision_text' => $record->recent_revision_text,
            'recent_revision_result' => $record->recent_revision_result?->value,
            'recent_revision_score' => $record->recent_revision_score,
            'old_revision_text' => $record->old_revision_text,
            'old_revision_result' => $record->old_revision_result?->value,
            'old_revision_score' => $record->old_revision_score,
            'mistake_count' => (int) $record->mistake_count,
            'haraka_mistakes' => (int) $record->haraka_mistakes,
            'word_mistakes' => (int) $record->word_mistakes,
            'fluency_mistakes' => (int) $record->fluency_mistakes,
            'teacher_note' => $record->teacher_note,
            'parent_visible_note' => $record->parent_visible_note,
            'supervisor_note' => $record->supervisor_note,
            'next_target' => $record->next_target,
            'requires_parent_attention' => (bool) $record->requires_parent_attention,
            'requires_supervisor_review' => (bool) $record->requires_supervisor_review,
            'overall_status' => $record->overall_status?->value,
            'reviewed_at' => $record->reviewed_at?->toDateTimeString(),
        ];
    }
}
