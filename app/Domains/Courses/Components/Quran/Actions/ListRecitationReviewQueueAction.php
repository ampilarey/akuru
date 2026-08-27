<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Support\Contracts\QuranReferenceReader;

/**
 * F4 teacher surface (§52.10 non-AI subset): the review queue, oldest first.
 * Student names via the People action, surah names via the reference
 * contract — no cross-boundary models (rule 3).
 */
class ListRecitationReviewQueueAction
{
    /**
     * @return array{rows: list<array<string, mixed>>, statuses: list<string>}
     */
    public function execute(?string $status = 'submitted'): array
    {
        $query = QuranRecitationSubmission::query()->withCount('mistakeMarks');
        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        $rows = $query->orderBy('submitted_at')->limit(200)->get();

        $students = app(ListStudentsByIdsAction::class)
            ->execute($rows->pluck('student_id')->all())
            ->keyBy('id');
        $surahs = collect(app(QuranReferenceReader::class)->listSurahs())->keyBy('id');

        return [
            'rows' => $rows
                ->map(fn (QuranRecitationSubmission $row): array => [
                    'id' => $row->id,
                    'student' => $students->get((int) $row->student_id),
                    'surah' => $row->surah_id
                        ? ($surahs->get((int) $row->surah_id)['english_name'] ?? null)
                        : null,
                    'start_ayah_number' => $row->start_ayah_number,
                    'end_ayah_number' => $row->end_ayah_number,
                    'mode' => $row->mode?->value,
                    'status' => $row->status?->value,
                    'submitted_at' => $row->submitted_at?->toDateTimeString(),
                    'reviewed_at' => $row->reviewed_at?->toDateTimeString(),
                    'review_note' => $row->review_note,
                    'mistake_count' => (int) $row->mistake_marks_count,
                ])
                ->values()
                ->all(),
            'statuses' => ['submitted', 'teacher_reviewed', 'needs_repeat', 'passed', 'failed', 'all'],
        ];
    }
}
